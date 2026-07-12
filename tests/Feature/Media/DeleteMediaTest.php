<?php

use Baobab\Media\Actions\DeleteMedia;
use Baobab\Media\Actions\UploadMedia;
use Baobab\Media\Models\Media;
use Baobab\Users\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

function deleteMediaActor(): User
{
    static $counter = 0;
    $counter++;

    return User::create([
        'name' => "Delete Media Actor {$counter}",
        'email' => "delete-media-actor-{$counter}@example.com",
        'password' => 'secret',
    ]);
}

it('soft deletes a media, leaving the file on disk', function () {
    $media = app(UploadMedia::class)(
        new UploadedFile(createTestJpeg(), 'photo.jpg', 'image/jpeg', null, true),
        deleteMediaActor(),
    );
    $path = $media->path;

    app(DeleteMedia::class)($media);

    expect(Media::find($media->id))->toBeNull()
        ->and(Media::withTrashed()->find($media->id))->not->toBeNull();

    Storage::disk('public')->assertExists($path);
});
