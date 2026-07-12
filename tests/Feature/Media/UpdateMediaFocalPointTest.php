<?php

use Baobab\Media\Actions\UpdateMediaFocalPoint;
use Baobab\Media\Actions\UploadMedia;
use Baobab\Media\Conversions\PresetRegistry;
use Baobab\Users\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

function focalPointActor(): User
{
    static $counter = 0;
    $counter++;

    return User::create([
        'name' => "Focal Point Actor {$counter}",
        'email' => "focal-point-actor-{$counter}@example.com",
        'password' => 'secret',
    ]);
}

it('persists focal_x and focal_y clamped between 0 and 1', function () {
    $media = app(UploadMedia::class)(
        new UploadedFile(createTestJpeg(), 'photo.jpg', 'image/jpeg', null, true),
        focalPointActor(),
    );

    $updated = app(UpdateMediaFocalPoint::class)($media, -0.2, 1.4);

    expect($updated->focal_x)->toBe(0.0)
        ->and($updated->focal_y)->toBe(1.0);
});

it('regenerates an already-generated crop preset to reflect the new focal point', function () {
    app(PresetRegistry::class)->register('focal-square', ['width' => 50, 'height' => 50, 'fit' => 'crop']);
    $media = app(UploadMedia::class)(
        new UploadedFile(createSplitColorTestJpeg(), 'split.jpg', 'image/jpeg', null, true),
        focalPointActor(),
    );

    app(UpdateMediaFocalPoint::class)($media, 0.0, 0.5);
    $leftCropPath = Storage::disk('public')->path($media->fresh()?->conversions['focal-square']['formats']['jpg']);
    $leftImage = imagecreatefromjpeg($leftCropPath);
    $leftColor = imagecolorsforindex($leftImage, imagecolorat($leftImage, 5, 25));

    app(UpdateMediaFocalPoint::class)($media, 1.0, 0.5);
    $rightCropPath = Storage::disk('public')->path($media->fresh()?->conversions['focal-square']['formats']['jpg']);
    $rightImage = imagecreatefromjpeg($rightCropPath);
    $rightColor = imagecolorsforindex($rightImage, imagecolorat($rightImage, 5, 25));

    expect($leftColor['red'])->toBeGreaterThan($leftColor['blue'])
        ->and($rightColor['blue'])->toBeGreaterThan($rightColor['red']);
});
