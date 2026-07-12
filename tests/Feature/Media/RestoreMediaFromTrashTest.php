<?php

use Baobab\Media\Actions\DeleteMedia;
use Baobab\Media\Actions\RestoreMediaFromTrash;
use Baobab\Media\Actions\UploadMedia;
use Baobab\Media\Models\Media;
use Baobab\Users\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

function restoreMediaActor(): User
{
    static $counter = 0;
    $counter++;

    return User::create([
        'name' => "Restore Media Actor {$counter}",
        'email' => "restore-media-actor-{$counter}@example.com",
        'password' => 'secret',
    ]);
}

it('restores a soft-deleted media', function () {
    $media = app(UploadMedia::class)(
        new UploadedFile(createTestJpeg(), 'photo.jpg', 'image/jpeg', null, true),
        restoreMediaActor(),
    );
    app(DeleteMedia::class)($media);

    expect(Media::find($media->id))->toBeNull();

    app(RestoreMediaFromTrash::class)($media);

    expect(Media::find($media->id))->not->toBeNull();
});
