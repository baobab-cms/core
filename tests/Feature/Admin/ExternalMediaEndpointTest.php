<?php

use Baobab\Access\Actions\GrantPermission;
use Baobab\Media\Models\Media;
use Baobab\Users\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

/**
 * @param  list<string>  $permissions
 */
function externalEndpointActor(array $permissions): User
{
    static $counter = 0;
    $counter++;

    $user = User::create([
        'name' => "External Endpoint Actor {$counter}",
        'email' => "external-endpoint-actor-{$counter}@example.com",
        'password' => 'secret',
    ]);

    app(GrantPermission::class)($user, 'baobab.admin.access');

    foreach ($permissions as $permission) {
        app(GrantPermission::class)($user, $permission);
    }

    return $user;
}

it('creates an external media through the admin endpoint', function () {
    Http::fake([
        'https://www.youtube.com/oembed*' => Http::response([
            'type' => 'video',
            'title' => 'Endpoint Video',
            'html' => '<iframe src="https://www.youtube.com/embed/xyz"></iframe>',
            'thumbnail_url' => 'https://i.ytimg.com/vi/xyz/hqdefault.jpg',
        ]),
        'https://i.ytimg.com/*' => Http::response(
            (string) file_get_contents(createTestJpeg(120, 90)),
            200,
            ['Content-Type' => 'image/jpeg'],
        ),
    ]);

    $user = externalEndpointActor(['baobab.media.upload']);

    $this->actingAs($user, 'baobab')
        ->postJson(route('admin.media.external.store'), ['url' => 'https://www.youtube.com/watch?v=xyz'])
        ->assertCreated()
        ->assertJsonPath('source', 'external');

    expect(Media::where('source', 'external')->count())->toBe(1);
});

it('denies the endpoint without the upload permission', function () {
    $user = externalEndpointActor([]);

    $this->actingAs($user, 'baobab')
        ->postJson(route('admin.media.external.store'), ['url' => 'https://www.youtube.com/watch?v=xyz'])
        ->assertForbidden();
});

it('returns 422 for an URL no provider supports', function () {
    $user = externalEndpointActor(['baobab.media.upload']);

    $this->actingAs($user, 'baobab')
        ->postJson(route('admin.media.external.store'), ['url' => 'https://example.com/clip'])
        ->assertUnprocessable();
});
