<?php

use Baobab\Access\Actions\GrantPermission;
use Baobab\Media\Actions\CreateMediaFolder;
use Baobab\Media\Models\Media;
use Baobab\Media\Models\MediaFolder;
use Baobab\Users\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    Storage::fake('public');
});

/**
 * @param  list<string>  $permissions
 */
function mediaActor(array $permissions): User
{
    static $counter = 0;
    $counter++;

    $user = User::create([
        'name' => "Media Actor {$counter}",
        'email' => "media-actor-{$counter}@example.com",
        'password' => 'secret',
    ]);

    app(GrantPermission::class)($user, 'baobab.admin.access');

    foreach ($permissions as $permission) {
        app(GrantPermission::class)($user, $permission);
    }

    return $user;
}

it('uploads a media file over HTTP for a user with baobab.media.upload', function () {
    $user = mediaActor(['baobab.media.upload']);
    $file = new UploadedFile(createTestJpeg(), 'photo.jpg', 'image/jpeg', null, true);

    $response = $this->actingAs($user, 'baobab')
        ->post(route('admin.media.store'), ['file' => $file])
        ->assertCreated();

    expect(Media::where('author_id', $user->id)->exists())->toBeTrue();
    $response->assertJsonPath('mime_type', 'image/jpeg');
});

it('denies upload without baobab.media.upload', function () {
    $user = mediaActor([]);
    $file = new UploadedFile(createTestJpeg(), 'photo.jpg', 'image/jpeg', null, true);

    $this->actingAs($user, 'baobab')
        ->post(route('admin.media.store'), ['file' => $file])
        ->assertForbidden();
});

it('denies SVG upload without baobab.media.upload_svg even with baobab.media.upload', function () {
    $user = mediaActor(['baobab.media.upload']);
    $svgPath = sys_get_temp_dir().'/baobab-test-plain.svg';
    file_put_contents($svgPath, '<svg xmlns="http://www.w3.org/2000/svg"><rect width="1" height="1" /></svg>');
    $file = new UploadedFile($svgPath, 'plain.svg', 'image/svg+xml', null, true);

    $this->actingAs($user, 'baobab')
        ->post(route('admin.media.store'), ['file' => $file])
        ->assertForbidden();
});

it('allows SVG upload with baobab.media.upload_svg', function () {
    $user = mediaActor(['baobab.media.upload', 'baobab.media.upload_svg']);
    $svgPath = sys_get_temp_dir().'/baobab-test-plain2.svg';
    file_put_contents($svgPath, '<svg xmlns="http://www.w3.org/2000/svg"><rect width="1" height="1" /></svg>');
    $file = new UploadedFile($svgPath, 'plain2.svg', 'image/svg+xml', null, true);

    $this->actingAs($user, 'baobab')
        ->post(route('admin.media.store'), ['file' => $file])
        ->assertCreated();
});

it('lets the owner update their own media with baobab.media.update', function () {
    $owner = mediaActor(['baobab.media.upload', 'baobab.media.update']);
    $file = new UploadedFile(createTestJpeg(), 'photo.jpg', 'image/jpeg', null, true);

    $this->actingAs($owner, 'baobab')->post(route('admin.media.store'), ['file' => $file]);
    $media = Media::where('author_id', $owner->id)->firstOrFail();

    expect($owner->can('update', $media))->toBeTrue();
});

it('forbids updating another user\'s media without update_any', function () {
    $owner = mediaActor(['baobab.media.upload']);
    $other = mediaActor(['baobab.media.upload', 'baobab.media.update']);
    $file = new UploadedFile(createTestJpeg(), 'photo.jpg', 'image/jpeg', null, true);

    $this->actingAs($owner, 'baobab')->post(route('admin.media.store'), ['file' => $file]);
    $media = Media::where('author_id', $owner->id)->firstOrFail();

    expect($other->can('update', $media))->toBeFalse();
});

it('allows updating another user\'s media with update_any', function () {
    $owner = mediaActor(['baobab.media.upload']);
    $manager = mediaActor(['baobab.media.upload', 'baobab.media.update_any']);
    $file = new UploadedFile(createTestJpeg(), 'photo.jpg', 'image/jpeg', null, true);

    $this->actingAs($owner, 'baobab')->post(route('admin.media.store'), ['file' => $file]);
    $media = Media::where('author_id', $owner->id)->firstOrFail();

    expect($manager->can('update', $media))->toBeTrue();
});

// ── index (grille) ───────────────────────────────────────────────────────────

it('shows the media library grid to a user with baobab.media.view', function () {
    $user = mediaActor(['baobab.media.view', 'baobab.media.upload']);
    $this->actingAs($user, 'baobab')->post(route('admin.media.store'), [
        'file' => new UploadedFile(createTestJpeg(), 'grid-photo.jpg', 'image/jpeg', null, true),
    ]);

    $this->actingAs($user, 'baobab')
        ->get(route('admin.media.index'))
        ->assertOk()
        ->assertSee('grid-photo.jpg');
});

it('denies the media library without baobab.media.view', function () {
    $user = mediaActor([]);

    $this->actingAs($user, 'baobab')
        ->get(route('admin.media.index'))
        ->assertForbidden();
});

// ── upload par chunks (HTTP) ──────────────────────────────────────────────────

it('assembles a chunked upload sent as two sequential HTTP requests', function () {
    $user = mediaActor(['baobab.media.upload']);
    $jpegPath = createTestJpeg(80, 40);
    $bytes = (string) file_get_contents($jpegPath);
    $midpoint = (int) (strlen($bytes) / 2);
    $uploadId = (string) Str::uuid();

    $chunk0Path = sys_get_temp_dir().'/baobab-test-chunk0.bin';
    $chunk1Path = sys_get_temp_dir().'/baobab-test-chunk1.bin';
    file_put_contents($chunk0Path, substr($bytes, 0, $midpoint));
    file_put_contents($chunk1Path, substr($bytes, $midpoint));

    $this->actingAs($user, 'baobab')
        ->post(route('admin.media.chunk'), [
            'upload_id' => $uploadId,
            'chunk_index' => 0,
            'total_chunks' => 2,
            'file_name' => 'chunked-photo.jpg',
            'chunk' => new UploadedFile($chunk0Path, 'chunk0', null, null, true),
        ])
        ->assertStatus(202);

    $response = $this->actingAs($user, 'baobab')
        ->post(route('admin.media.chunk'), [
            'upload_id' => $uploadId,
            'chunk_index' => 1,
            'total_chunks' => 2,
            'file_name' => 'chunked-photo.jpg',
            'chunk' => new UploadedFile($chunk1Path, 'chunk1', null, null, true),
        ])
        ->assertCreated();

    $media = Media::where('file_name', 'chunked-photo.jpg')->firstOrFail();
    expect($media->width)->toBe(80)
        ->and($media->height)->toBe(40);

    $response->assertJsonPath('file_name', 'chunked-photo.jpg');
});

// ── dossiers (HTTP) ────────────────────────────────────────────────────────────

it('creates, renames and deletes a folder over HTTP with baobab.media.upload', function () {
    $user = mediaActor(['baobab.media.upload']);

    $this->actingAs($user, 'baobab')
        ->post(route('admin.media.folders.store'), ['name' => 'Photos'])
        ->assertRedirect();

    $folder = MediaFolder::where('name', 'Photos')->firstOrFail();

    $this->actingAs($user, 'baobab')
        ->put(route('admin.media.folders.update', ['folder' => $folder->id]), ['name' => 'Images'])
        ->assertRedirect();

    expect($folder->fresh()?->name)->toBe('Images');

    $this->actingAs($user, 'baobab')
        ->delete(route('admin.media.folders.destroy', ['folder' => $folder->id]))
        ->assertRedirect();

    expect(MediaFolder::find($folder->id))->toBeNull();
});

it('denies folder management without baobab.media.upload', function () {
    $user = mediaActor([]);

    $this->actingAs($user, 'baobab')
        ->post(route('admin.media.folders.store'), ['name' => 'Photos'])
        ->assertForbidden();
});

// ── déplacement groupé ─────────────────────────────────────────────────────────

it('moves selected media into a folder, skipping items the actor cannot update', function () {
    $owner = mediaActor(['baobab.media.upload']);
    $mover = mediaActor(['baobab.media.upload', 'baobab.media.update_any']);

    $this->actingAs($owner, 'baobab')->post(route('admin.media.store'), [
        'file' => new UploadedFile(createTestJpeg(), 'own.jpg', 'image/jpeg', null, true),
    ]);
    $media = Media::where('file_name', 'own.jpg')->firstOrFail();

    $folder = app(CreateMediaFolder::class)('Destination');

    $this->actingAs($mover, 'baobab')
        ->post(route('admin.media.move'), ['ids' => [$media->id], 'folder_id' => $folder->id])
        ->assertRedirect();

    expect($media->fresh()?->folder_id)->toBe($folder->id);
});

// ── doublons (HTTP) ────────────────────────────────────────────────────────────

it('responds 409 with the existing media when a duplicate is uploaded over HTTP', function () {
    $user = mediaActor(['baobab.media.upload']);

    $this->actingAs($user, 'baobab')->post(route('admin.media.store'), [
        'file' => new UploadedFile(createTestJpeg(), 'first.jpg', 'image/jpeg', null, true),
    ]);

    $response = $this->actingAs($user, 'baobab')
        ->post(route('admin.media.store'), [
            'file' => new UploadedFile(createTestJpeg(), 'second.jpg', 'image/jpeg', null, true),
        ]);

    $response->assertStatus(409);
    expect(Media::count())->toBe(1);
});

it('reuses the existing media over HTTP when duplicate_action is reuse', function () {
    $user = mediaActor(['baobab.media.upload']);

    $this->actingAs($user, 'baobab')->post(route('admin.media.store'), [
        'file' => new UploadedFile(createTestJpeg(), 'first.jpg', 'image/jpeg', null, true),
    ]);

    $this->actingAs($user, 'baobab')
        ->post(route('admin.media.store'), [
            'file' => new UploadedFile(createTestJpeg(), 'second.jpg', 'image/jpeg', null, true),
            'duplicate_action' => 'reuse',
        ])
        ->assertCreated();

    expect(Media::count())->toBe(1);
});
