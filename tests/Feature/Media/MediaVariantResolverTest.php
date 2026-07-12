<?php

use Baobab\Media\Actions\UploadMedia;
use Baobab\Media\Conversions\MediaVariantResolver;
use Baobab\Media\Conversions\PresetRegistry;
use Baobab\Users\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

function resolverTestActor(): User
{
    static $counter = 0;
    $counter++;

    return User::create([
        'name' => "Resolver Actor {$counter}",
        'email' => "resolver-actor-{$counter}@example.com",
        'password' => 'secret',
    ]);
}

it('returns the URL directly when the preset already has a variant', function () {
    $actor = resolverTestActor();
    $media = app(UploadMedia::class)(
        new UploadedFile(createTestJpeg(400, 300), 'photo.jpg', 'image/jpeg', null, true),
        $actor,
    );

    $url = app(MediaVariantResolver::class)->resolve($media->fresh(), 'thumb');

    expect($url)->not->toBeNull()
        ->and($url)->toContain('thumb');
});

it('generates a preset added after upload on first resolve, then reuses it', function () {
    $actor = resolverTestActor();
    $media = app(UploadMedia::class)(
        new UploadedFile(createTestJpeg(400, 300), 'photo.jpg', 'image/jpeg', null, true),
        $actor,
    )->fresh();

    expect($media->conversions)->not->toHaveKey('square-later');

    app(PresetRegistry::class)->register('square-later', ['width' => 40, 'height' => 40, 'fit' => 'crop']);

    $resolver = app(MediaVariantResolver::class);
    $firstUrl = $resolver->resolve($media, 'square-later');

    expect($firstUrl)->not->toBeNull();
    expect($media->fresh()?->conversions)->toHaveKey('square-later');

    $secondUrl = $resolver->resolve($media->fresh(), 'square-later');
    expect($secondUrl)->toBe($firstUrl);
});

it('returns null for an unregistered preset', function () {
    $actor = resolverTestActor();
    $media = app(UploadMedia::class)(
        new UploadedFile(createTestJpeg(), 'photo.jpg', 'image/jpeg', null, true),
        $actor,
    );

    expect(app(MediaVariantResolver::class)->resolve($media->fresh(), 'does-not-exist'))->toBeNull();
});
