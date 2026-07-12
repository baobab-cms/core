<?php

use Baobab\Media\Actions\CreateMediaFolder;
use Baobab\Media\Actions\DeleteMediaFolder;
use Baobab\Media\Actions\RenameMediaFolder;
use Baobab\Media\Exceptions\FolderNotEmptyException;
use Baobab\Media\Models\Media;
use Baobab\Media\Models\MediaFolder;
use Baobab\Users\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

it('creates a nested folder', function () {
    $root = app(CreateMediaFolder::class)('Photos');
    $child = app(CreateMediaFolder::class)('2026', $root->id);

    expect($child->parent_id)->toBe($root->id)
        ->and($child->parent->name)->toBe('Photos');
});

it('renames a folder', function () {
    $folder = app(CreateMediaFolder::class)('Photos');

    app(RenameMediaFolder::class)($folder, 'Images');

    expect($folder->fresh()?->name)->toBe('Images');
});

it('refuses to delete a folder that still has subfolders', function () {
    $root = app(CreateMediaFolder::class)('Photos');
    app(CreateMediaFolder::class)('2026', $root->id);

    expect(fn () => app(DeleteMediaFolder::class)($root))
        ->toThrow(FolderNotEmptyException::class);

    expect(MediaFolder::find($root->id))->not->toBeNull();
});

it('orphans contained media to the root when a folder is deleted', function () {
    $folder = app(CreateMediaFolder::class)('Photos');

    $user = User::create(['name' => 'Uploader', 'email' => 'folder-owner@example.com', 'password' => 'secret']);
    $media = Media::create([
        'disk' => 'public',
        'path' => 'media/2026/07/test.jpg',
        'file_name' => 'test.jpg',
        'mime_type' => 'image/jpeg',
        'size' => 10,
        'checksum' => hash('sha256', 'irrelevant'),
        'folder_id' => $folder->id,
        'author_id' => $user->id,
    ]);

    app(DeleteMediaFolder::class)($folder);

    expect(MediaFolder::find($folder->id))->toBeNull()
        ->and($media->fresh()?->folder_id)->toBeNull();
});
