<?php

use Baobab\Media\Actions\RestoreOriginalMedia;
use Baobab\Media\Actions\TransformMedia;
use Baobab\Media\Actions\UploadMedia;
use Baobab\Users\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

function restoreActor(): User
{
    static $counter = 0;
    $counter++;

    return User::create([
        'name' => "Restore Actor {$counter}",
        'email' => "restore-actor-{$counter}@example.com",
        'password' => 'secret',
    ]);
}

it('clears the edited columns, deletes the edited file and regenerates presets from the true original', function () {
    $media = app(UploadMedia::class)(
        new UploadedFile(createTestJpeg(40, 20), 'photo.jpg', 'image/jpeg', null, true),
        restoreActor(),
    );

    $edited = app(TransformMedia::class)($media, new UploadedFile(createTestJpeg(30, 30), 'edit.jpg', 'image/jpeg', null, true));
    $editedPath = $edited->edited_path;

    expect($edited->fresh()?->conversions['thumb']['width'])->toBe(30);

    app(RestoreOriginalMedia::class)($edited->fresh());
    $restored = $media->fresh();

    expect($restored->edited_path)->toBeNull()
        ->and($restored->edited_width)->toBeNull()
        ->and($restored->edited_height)->toBeNull()
        ->and($restored->conversions['thumb']['width'])->toBe(40);

    Storage::disk('public')->assertMissing($editedPath);
    Storage::disk('public')->assertExists($restored->path);
});
