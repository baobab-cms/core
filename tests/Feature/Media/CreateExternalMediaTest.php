<?php

use Baobab\Facades\Hook;
use Baobab\Media\Actions\CreateExternalMedia;
use Baobab\Media\Exceptions\DuplicateMediaDetectedException;
use Baobab\Media\Exceptions\ExternalMediaException;
use Baobab\Media\Models\Media;
use Baobab\Users\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

function externalMediaActor(): User
{
    static $counter = 0;
    $counter++;

    return User::create([
        'name' => "External Media Actor {$counter}",
        'email' => "external-media-actor-{$counter}@example.com",
        'password' => 'secret',
    ]);
}

function fakeYoutubeOembed(): void
{
    Http::fake([
        'https://www.youtube.com/oembed*' => Http::response([
            'type' => 'video',
            'title' => 'Baobab Demo Video',
            'html' => '<iframe src="https://www.youtube.com/embed/abc123"></iframe>',
            'thumbnail_url' => 'https://i.ytimg.com/vi/abc123/hqdefault.jpg',
            'width' => 200,
            'height' => 113,
        ]),
        'https://i.ytimg.com/*' => Http::response(
            (string) file_get_contents(createTestJpeg(480, 360)),
            200,
            ['Content-Type' => 'image/jpeg'],
        ),
    ]);
}

it('creates an external media from a YouTube URL, thumbnail stored as its file', function () {
    fakeYoutubeOembed();

    $media = app(CreateExternalMedia::class)(
        'https://www.youtube.com/watch?v=abc123',
        externalMediaActor(),
    );

    expect($media->source)->toBe('external')
        ->and($media->isExternal())->toBeTrue()
        ->and($media->external_url)->toBe('https://www.youtube.com/watch?v=abc123')
        ->and($media->mime_type)->toBe('video/external')
        ->and($media->file_name)->toBe('Baobab Demo Video')
        ->and($media->title)->toBe('Baobab Demo Video')
        ->and($media->alt)->toBe('Baobab Demo Video')
        ->and($media->width)->toBe(480)
        ->and($media->height)->toBe(360)
        ->and($media->externalEmbedHtml())->toContain('youtube.com/embed/abc123')
        ->and($media->meta['oembed']['provider'])->toBe('youtube');

    Storage::disk('public')->assertExists($media->path);
});

it('rejects an URL that no whitelisted provider matches', function () {
    app(CreateExternalMedia::class)('https://example.com/video/42', externalMediaActor());
})->throws(ExternalMediaException::class);

it('fails cleanly when the oEmbed endpoint errors', function () {
    Http::fake(['https://www.youtube.com/oembed*' => Http::response(null, 404)]);

    app(CreateExternalMedia::class)('https://youtu.be/abc123', externalMediaActor());
})->throws(ExternalMediaException::class);

it('fails cleanly when the provider returns no usable thumbnail', function () {
    Http::fake([
        'https://www.youtube.com/oembed*' => Http::response([
            'type' => 'video',
            'title' => 'No Thumb',
            'html' => '<iframe></iframe>',
        ]),
    ]);

    app(CreateExternalMedia::class)('https://youtu.be/abc123', externalMediaActor());
})->throws(ExternalMediaException::class);

it('detects a duplicate of the same external URL (tri-state, like uploads)', function () {
    fakeYoutubeOembed();
    $actor = externalMediaActor();
    $url = 'https://www.youtube.com/watch?v=abc123';

    $first = app(CreateExternalMedia::class)($url, $actor);

    // Défaut 'ask' : le doublon est signalé à l'appelant.
    expect(fn () => app(CreateExternalMedia::class)($url, $actor))
        ->toThrow(DuplicateMediaDetectedException::class);

    // 'reuse' : l'existant est retourné, rien de créé.
    $reused = app(CreateExternalMedia::class)($url, $actor, [], 'reuse');
    expect($reused->id)->toBe($first->id)
        ->and(Media::count())->toBe(1);

    // 'new' : le doublon est accepté sciemment.
    app(CreateExternalMedia::class)($url, $actor, [], 'new');
    expect(Media::count())->toBe(2);
});

it('lets a module register its own oEmbed provider via the filter hook', function () {
    Hook::modify('baobab.media.oembed.providers', function (array $providers): array {
        $providers['acme'] = [
            'hosts' => ['videos.acme.test'],
            'endpoint' => 'https://videos.acme.test/oembed',
        ];

        return $providers;
    });

    Http::fake([
        'https://videos.acme.test/oembed*' => Http::response([
            'type' => 'video',
            'title' => 'Acme Clip',
            'html' => '<iframe src="https://videos.acme.test/embed/1"></iframe>',
            'thumbnail_url' => 'https://videos.acme.test/thumb/1.jpg',
        ]),
        'https://videos.acme.test/thumb/*' => Http::response(
            (string) file_get_contents(createTestJpeg(100, 50)),
            200,
            ['Content-Type' => 'image/jpeg'],
        ),
    ]);

    $media = app(CreateExternalMedia::class)('https://videos.acme.test/watch/1', externalMediaActor());

    expect($media->meta['oembed']['provider'])->toBe('acme')
        ->and($media->title)->toBe('Acme Clip');
});
