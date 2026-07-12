<?php

use Baobab\Media\Actions\UploadMedia;
use Baobab\Media\Conversions\GenerateMediaConversions;
use Baobab\Media\Conversions\PresetRegistry;
use Baobab\Users\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

function conversionsTestActor(): User
{
    static $counter = 0;
    $counter++;

    return User::create([
        'name' => "Conversions Actor {$counter}",
        'email' => "conversions-actor-{$counter}@example.com",
        'password' => 'secret',
    ]);
}

it('generates thumb/medium/large variants (original format + webp) on upload', function () {
    $actor = conversionsTestActor();
    $media = app(UploadMedia::class)(
        new UploadedFile(createTestJpeg(2000, 1000), 'photo.jpg', 'image/jpeg', null, true),
        $actor,
    );

    $media = $media->fresh();
    expect($media)->not->toBeNull();

    foreach (['thumb', 'medium', 'large'] as $preset) {
        expect($media->conversions)->toHaveKey($preset);
        $formats = $media->conversions[$preset]['formats'];
        expect($formats)->toHaveKey('jpg')
            ->toHaveKey('webp');

        Storage::disk('public')->assertExists($formats['jpg']);
        Storage::disk('public')->assertExists($formats['webp']);
    }
});

it('never upscales a contain preset beyond the original dimensions', function () {
    $actor = conversionsTestActor();
    // 20x10 est plus petit que le preset thumb (largeur 300).
    $media = app(UploadMedia::class)(
        new UploadedFile(createTestJpeg(20, 10), 'small.jpg', 'image/jpeg', null, true),
        $actor,
    );

    $media = $media->fresh();
    expect($media->conversions['thumb']['width'])->toBe(20)
        ->and($media->conversions['thumb']['height'])->toBe(10);
});

it('crops around the focal point instead of always centering', function () {
    app(PresetRegistry::class)->register('split-square', ['width' => 50, 'height' => 50, 'fit' => 'crop']);
    $actor = conversionsTestActor();

    $leftFocused = app(UploadMedia::class)(
        new UploadedFile(createSplitColorTestJpeg(), 'left.jpg', 'image/jpeg', null, true),
        $actor,
    );
    $leftFocused->update(['focal_x' => 0.0, 'focal_y' => 0.5]);
    app()->call([new GenerateMediaConversions($leftFocused, 'split-square', true), 'handle']);

    $rightFocused = app(UploadMedia::class)(
        new UploadedFile(createSplitColorTestJpeg(), 'right.jpg', 'image/jpeg', null, true),
        $actor,
        [],
        'new',
    );
    $rightFocused->update(['focal_x' => 1.0, 'focal_y' => 0.5]);
    app()->call([new GenerateMediaConversions($rightFocused, 'split-square', true), 'handle']);

    $leftFocused = $leftFocused->fresh();
    $rightFocused = $rightFocused->fresh();

    $leftCropPath = Storage::disk('public')->path($leftFocused->conversions['split-square']['formats']['jpg']);
    $rightCropPath = Storage::disk('public')->path($rightFocused->conversions['split-square']['formats']['jpg']);

    $leftImage = imagecreatefromjpeg($leftCropPath);
    $rightImage = imagecreatefromjpeg($rightCropPath);

    $leftColor = imagecolorsforindex($leftImage, imagecolorat($leftImage, 5, 25));
    $rightColor = imagecolorsforindex($rightImage, imagecolorat($rightImage, 5, 25));
    [$leftR, $leftB] = [$leftColor['red'], $leftColor['blue']];
    [$rightR, $rightB] = [$rightColor['red'], $rightColor['blue']];

    expect($leftR)->toBeGreaterThan($leftB)
        ->and($rightB)->toBeGreaterThan($rightR);
});

it('does not attempt any conversion for an SVG upload', function () {
    $actor = conversionsTestActor();
    $svgPath = sys_get_temp_dir().'/baobab-test-conversions.svg';
    file_put_contents($svgPath, '<svg xmlns="http://www.w3.org/2000/svg"><rect width="1" height="1" /></svg>');

    $media = app(UploadMedia::class)(new UploadedFile($svgPath, 'icon.svg', 'image/svg+xml', null, true), $actor);

    expect($media->fresh()?->conversions)->toBe([]);
});
