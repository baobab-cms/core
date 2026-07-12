<?php

use Baobab\Media\Actions\CreateMediaFolder;
use Baobab\Media\Actions\MoveMedia;
use Baobab\Media\Models\Media;
use Baobab\Users\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

it('moves media into a folder and back to the root', function () {
    $folder = app(CreateMediaFolder::class)('Photos');
    $user = User::create(['name' => 'Uploader', 'email' => 'move-owner@example.com', 'password' => 'secret']);

    $media = Media::create([
        'disk' => 'public',
        'path' => 'media/2026/07/test.jpg',
        'file_name' => 'test.jpg',
        'mime_type' => 'image/jpeg',
        'size' => 10,
        'checksum' => hash('sha256', 'irrelevant'),
        'author_id' => $user->id,
    ]);

    app(MoveMedia::class)([$media], $folder->id);
    expect($media->fresh()?->folder_id)->toBe($folder->id);

    app(MoveMedia::class)([$media], null);
    expect($media->fresh()?->folder_id)->toBeNull();
});
