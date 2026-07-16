<?php

use Baobab\Access\Actions\GrantPermission;
use Baobab\Notify\Notifications\BaobabNotification;
use Baobab\Users\Models\User;
use Illuminate\Support\Facades\Notification;

function centerActor(): User
{
    static $counter = 0;
    $counter++;

    $user = User::create([
        'name' => "Center Actor {$counter}",
        'email' => "center-actor-{$counter}@example.com",
        'password' => 'secret',
    ]);

    app(GrantPermission::class)($user, 'baobab.admin.access');

    return $user;
}

it('reports the unread count and recent items via poll', function () {
    $actor = centerActor();
    Notification::sendNow($actor, new BaobabNotification('core.test', 'Un test', ['url' => '/foo']));

    $response = $this->actingAs($actor, 'baobab')->getJson(route('admin.notifications.poll'));

    $response->assertOk()
        ->assertJson(['count' => 1])
        ->assertJsonPath('items.0.description', 'Un test')
        ->assertJsonPath('items.0.url', '/foo')
        ->assertJsonPath('items.0.read', false);
});

it('marks a single notification as read, scoped to the acting user', function () {
    $actor = centerActor();
    $other = centerActor();
    Notification::sendNow($actor, new BaobabNotification('core.test', 'Un test', []));
    $notification = $actor->notifications()->sole();

    $this->actingAs($other, 'baobab')
        ->post(route('admin.notifications.read', $notification->id))
        ->assertForbidden();

    expect($notification->fresh()->read_at)->toBeNull();

    $this->actingAs($actor, 'baobab')
        ->post(route('admin.notifications.read', $notification->id))
        ->assertNoContent();

    expect($notification->fresh()->read_at)->not->toBeNull();
});

it('marks all of the acting user\'s notifications as read, not another user\'s', function () {
    $actor = centerActor();
    $other = centerActor();
    Notification::sendNow($actor, new BaobabNotification('core.test', 'Mine', []));
    Notification::sendNow($other, new BaobabNotification('core.test', 'Theirs', []));

    $this->actingAs($actor, 'baobab')
        ->post(route('admin.notifications.read-all'))
        ->assertNoContent();

    expect($actor->unreadNotifications()->count())->toBe(0)
        ->and($other->unreadNotifications()->count())->toBe(1);
});

it('lists the acting user\'s notifications on the full index screen', function () {
    $actor = centerActor();
    Notification::sendNow($actor, new BaobabNotification('core.test', 'Visible ici', []));

    $this->actingAs($actor, 'baobab')
        ->get(route('admin.notifications.index'))
        ->assertOk()
        ->assertSee('Visible ici');
});
