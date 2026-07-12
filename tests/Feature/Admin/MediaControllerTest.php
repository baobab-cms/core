<?php

use Baobab\Access\Actions\GrantPermission;
use Baobab\Media\Models\Media;
use Baobab\Users\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

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
