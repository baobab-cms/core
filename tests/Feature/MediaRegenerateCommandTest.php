<?php

use Baobab\Media\Actions\UploadMedia;
use Baobab\Media\Conversions\PresetRegistry;
use Baobab\Users\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

function regenerateCommandActor(): User
{
    static $counter = 0;
    $counter++;

    return User::create([
        'name' => "Regenerate Actor {$counter}",
        'email' => "regenerate-actor-{$counter}@example.com",
        'password' => 'secret',
    ]);
}

it('media:regenerate --missing only fills in absent variants', function () {
    $actor = regenerateCommandActor();
    $media = app(UploadMedia::class)(
        new UploadedFile(createTestJpeg(400, 300), 'photo.jpg', 'image/jpeg', null, true),
        $actor,
    )->fresh();

    $thumbPathBefore = $media->conversions['thumb']['formats']['jpg'];

    app(PresetRegistry::class)->register('regen-missing', ['width' => 40, 'fit' => 'contain']);

    Artisan::call('media:regenerate', ['--missing' => true]);

    $media = $media->fresh();
    expect($media->conversions)->toHaveKey('regen-missing')
        ->and($media->conversions['thumb']['formats']['jpg'])->toBe($thumbPathBefore);
});

it('media:regenerate --preset= targets a single preset', function () {
    $actor = regenerateCommandActor();
    $media = app(UploadMedia::class)(
        new UploadedFile(createTestJpeg(400, 300), 'photo.jpg', 'image/jpeg', null, true),
        $actor,
    )->fresh();

    $media->update(['conversions' => []]);

    Artisan::call('media:regenerate', ['--preset' => 'thumb']);

    $media = $media->fresh();
    expect($media->conversions)->toHaveKey('thumb')
        ->and($media->conversions)->not->toHaveKey('medium')
        ->and($media->conversions)->not->toHaveKey('large');
});
