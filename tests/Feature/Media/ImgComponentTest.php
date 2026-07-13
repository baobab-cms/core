<?php

use Baobab\Media\Actions\UploadMedia;
use Baobab\Media\Models\Media;
use Baobab\Users\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

function imgComponentActor(): User
{
    static $counter = 0;
    $counter++;

    return User::create([
        'name' => "Img Component Actor {$counter}",
        'email' => "img-component-actor-{$counter}@example.com",
        'password' => 'secret',
    ]);
}

function imgComponentMedia(int $width = 2000, int $height = 1000): Media
{
    $media = app(UploadMedia::class)(
        new UploadedFile(createTestJpeg($width, $height), 'photo.jpg', 'image/jpeg', null, true),
        imgComponentActor(),
        ['alt' => 'Une photo de test'],
        'new',
    );

    return $media->fresh() ?? $media;
}

it('renders a <picture> with WebP srcset, intrinsic dimensions and native lazy loading', function () {
    $media = imgComponentMedia();

    $html = Blade::render('<x-baobab::img :media="$media" preset="medium" />', ['media' => $media]);

    expect($html)->toContain('<picture>')
        ->toContain('type="image/webp"')
        ->toContain('768w')
        ->toContain('width="768"')
        ->toContain('loading="lazy"')
        ->toContain('decoding="async"')
        ->toContain('alt="Une photo de test"');

    // srcset responsive : plusieurs largeurs issues des presets de même ratio.
    expect(substr_count($html, 'w,'))->toBeGreaterThanOrEqual(2);
});

it('lets the caller override the alt text', function () {
    $media = imgComponentMedia();

    $html = Blade::render('<x-baobab::img :media="$media" preset="thumb" alt="Autre texte" />', ['media' => $media]);

    expect($html)->toContain('alt="Autre texte"');
});

it('renders nothing at all for a null media', function () {
    $html = Blade::render('<x-baobab::img :media="$media" preset="thumb" />', ['media' => null]);

    expect(trim($html))->toBe('');
});

it('falls back to a plain <img> when no preset is requested', function () {
    $media = imgComponentMedia(40, 20);

    $html = Blade::render('<x-baobab::img :media="$media" />', ['media' => $media]);

    expect($html)->not->toContain('<picture>')
        ->and($html)->toContain($media->url())
        ->and($html)->toContain('width="40"')
        ->and($html)->toContain('loading="lazy"');
});

it('renders the stored thumbnail as a plain <img> for an external media', function () {
    Storage::disk('public')->put('media/2026/07/ext.jpg', (string) file_get_contents(createTestJpeg(480, 360)));

    $media = Media::create([
        'disk' => 'public',
        'path' => 'media/2026/07/ext.jpg',
        'file_name' => 'Demo Video',
        'mime_type' => 'video/external',
        'source' => 'external',
        'external_url' => 'https://www.youtube.com/watch?v=abc123',
        'size' => 1000,
        'width' => 480,
        'height' => 360,
        'alt' => 'Demo Video',
        'checksum' => hash('sha256', 'external:https://www.youtube.com/watch?v=abc123'),
        'conversions' => [],
        'meta' => [],
    ]);

    $html = Blade::render('<x-baobab::img :media="$media" preset="thumb" />', ['media' => $media]);

    expect($html)->not->toContain('<picture>')
        ->and($html)->toContain('media/2026/07/ext.jpg')
        ->and($html)->toContain('alt="Demo Video"');
});

it('passes extra attributes through to the <img> tag', function () {
    $media = imgComponentMedia(40, 20);

    $html = Blade::render('<x-baobab::img :media="$media" class="rounded-lg" />', ['media' => $media]);

    expect($html)->toContain('class="rounded-lg"');
});
