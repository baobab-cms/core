<?php

use Baobab\Media\Actions\TransformMedia;
use Baobab\Media\Actions\UploadMedia;
use Baobab\Media\Exceptions\InvalidMediaUploadException;
use Baobab\Users\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

function transformActor(): User
{
    static $counter = 0;
    $counter++;

    return User::create([
        'name' => "Transform Actor {$counter}",
        'email' => "transform-actor-{$counter}@example.com",
        'password' => 'secret',
    ]);
}

it('stores the edited export without touching the original file or its metadata', function () {
    $media = app(UploadMedia::class)(
        new UploadedFile(createTestJpeg(40, 20), 'photo.jpg', 'image/jpeg', null, true),
        transformActor(),
    );

    $originalPath = $media->path;
    $originalChecksum = $media->checksum;

    $edit = new UploadedFile(createTestJpeg(30, 30), 'cropped.jpg', 'image/jpeg', null, true);
    $updated = app(TransformMedia::class)($media, $edit);

    expect($updated->edited_path)->not->toBeNull()
        ->and($updated->edited_width)->toBe(30)
        ->and($updated->edited_height)->toBe(30)
        ->and($updated->path)->toBe($originalPath)
        ->and($updated->width)->toBe(40)
        ->and($updated->height)->toBe(20)
        ->and($updated->checksum)->toBe($originalChecksum);

    Storage::disk('public')->assertExists($originalPath);
    Storage::disk('public')->assertExists($updated->edited_path);
});

it('replaces a previous edit and deletes its file instead of stacking edits', function () {
    $media = app(UploadMedia::class)(
        new UploadedFile(createTestJpeg(40, 20), 'photo.jpg', 'image/jpeg', null, true),
        transformActor(),
    );

    $first = app(TransformMedia::class)($media, new UploadedFile(createTestJpeg(30, 30), 'first-edit.jpg', 'image/jpeg', null, true));
    $firstEditedPath = $first->edited_path;

    // Export PNG au deuxième passage (plutôt que JPEG à nouveau) : preuve que l'ancien fichier
    // édité est bien supprimé même quand le nouveau chemin diffère (extension différente), pas
    // seulement écrasé silencieusement au même chemin.
    $pngPath = sys_get_temp_dir().'/baobab-test-second-edit.png';
    $image = imagecreatetruecolor(15, 15);
    imagepng($image, $pngPath);
    imagedestroy($image);

    $second = app(TransformMedia::class)($media->fresh(), new UploadedFile($pngPath, 'second-edit.png', 'image/png', null, true));

    expect($second->edited_path)->not->toBe($firstEditedPath)
        ->and($second->edited_width)->toBe(15);

    Storage::disk('public')->assertMissing($firstEditedPath);
    Storage::disk('public')->assertExists($second->edited_path);
});

it('rejects an edit export whose real content is not an allowed image MIME type', function () {
    $media = app(UploadMedia::class)(
        new UploadedFile(createTestJpeg(), 'photo.jpg', 'image/jpeg', null, true),
        transformActor(),
    );

    $fakePath = sys_get_temp_dir().'/baobab-test-fake-edit.jpg';
    file_put_contents($fakePath, 'this is plain text pretending to be a jpeg');
    $fake = new UploadedFile($fakePath, 'fake-edit.jpg', 'image/jpeg', null, true);

    expect(fn () => app(TransformMedia::class)($media, $fake))
        ->toThrow(InvalidMediaUploadException::class);

    expect($media->fresh()?->edited_path)->toBeNull();
});
