<?php

use Baobab\Media\Actions\UploadMedia;
use Baobab\Media\Exceptions\DuplicateMediaDetectedException;
use Baobab\Media\Models\Media;
use Baobab\Users\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

function duplicateTestActor(): User
{
    static $counter = 0;
    $counter++;

    return User::create([
        'name' => "Dup Uploader {$counter}",
        'email' => "dup-uploader-{$counter}@example.com",
        'password' => 'secret',
    ]);
}

it('asks by default when a checksum-identical file is uploaded again', function () {
    $actor = duplicateTestActor();
    $original = app(UploadMedia::class)(new UploadedFile(createTestJpeg(), 'photo.jpg', 'image/jpeg', null, true), $actor);

    expect(fn () => app(UploadMedia::class)(new UploadedFile(createTestJpeg(), 'photo-again.jpg', 'image/jpeg', null, true), $actor))
        ->toThrow(DuplicateMediaDetectedException::class);

    expect(Media::count())->toBe(1)
        ->and(Media::first()?->id)->toBe($original->id);
});

it('reuses the existing media and discards the freshly written file when duplicateAction is reuse', function () {
    $actor = duplicateTestActor();
    $original = app(UploadMedia::class)(new UploadedFile(createTestJpeg(), 'photo.jpg', 'image/jpeg', null, true), $actor);

    $result = app(UploadMedia::class)(
        new UploadedFile(createTestJpeg(), 'photo-again.jpg', 'image/jpeg', null, true),
        $actor,
        [],
        'reuse',
    );

    expect($result->id)->toBe($original->id)
        ->and(Media::count())->toBe(1);
});

it('creates a real duplicate when duplicateAction is new', function () {
    $actor = duplicateTestActor();
    app(UploadMedia::class)(new UploadedFile(createTestJpeg(), 'photo.jpg', 'image/jpeg', null, true), $actor);

    $second = app(UploadMedia::class)(
        new UploadedFile(createTestJpeg(), 'photo-again.jpg', 'image/jpeg', null, true),
        $actor,
        [],
        'new',
    );

    expect(Media::count())->toBe(2)
        ->and($second->file_name)->toBe('photo-again.jpg');
});

it('allows silent duplicates when baobab.media.duplicate_behavior is allow', function () {
    config(['baobab.media.duplicate_behavior' => 'allow']);
    $actor = duplicateTestActor();
    app(UploadMedia::class)(new UploadedFile(createTestJpeg(), 'photo.jpg', 'image/jpeg', null, true), $actor);

    app(UploadMedia::class)(new UploadedFile(createTestJpeg(), 'photo-again.jpg', 'image/jpeg', null, true), $actor);

    expect(Media::count())->toBe(2);
});

it('reuses silently when baobab.media.duplicate_behavior is reuse', function () {
    config(['baobab.media.duplicate_behavior' => 'reuse']);
    $actor = duplicateTestActor();
    $original = app(UploadMedia::class)(new UploadedFile(createTestJpeg(), 'photo.jpg', 'image/jpeg', null, true), $actor);

    $result = app(UploadMedia::class)(new UploadedFile(createTestJpeg(), 'photo-again.jpg', 'image/jpeg', null, true), $actor);

    expect($result->id)->toBe($original->id)
        ->and(Media::count())->toBe(1);
});
