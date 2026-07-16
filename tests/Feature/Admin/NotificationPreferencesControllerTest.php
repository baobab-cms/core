<?php

use Baobab\Access\Actions\GrantPermission;
use Baobab\Notify\Models\NotificationPreference;
use Baobab\Users\Models\User;

function preferencesActor(): User
{
    static $counter = 0;
    $counter++;

    $user = User::create([
        'name' => "Preferences Actor {$counter}",
        'email' => "preferences-actor-{$counter}@example.com",
        'password' => 'secret',
    ]);

    app(GrantPermission::class)($user, 'baobab.admin.access');

    return $user;
}

it('shows the preferences matrix restricted to configurable declarations', function () {
    config(['baobab.notifications.declarations' => [
        ['key' => 'core.configurable', 'description' => 'Configurable', 'channels' => ['database', 'mail'], 'configurable' => true],
        ['key' => 'core.locked', 'description' => 'Verrouillée', 'channels' => ['database'], 'configurable' => false],
    ]]);

    $actor = preferencesActor();

    $this->actingAs($actor, 'baobab')
        ->get(route('admin.account.notifications.show'))
        ->assertOk()
        ->assertSee('Configurable')
        ->assertDontSee('Verrouillée');
});

it('persists a disabled channel as a preference row', function () {
    config(['baobab.notifications.declarations' => [
        ['key' => 'core.configurable', 'channels' => ['database', 'mail'], 'configurable' => true],
    ]]);

    $actor = preferencesActor();

    $this->actingAs($actor, 'baobab')
        ->post(route('admin.account.notifications.update'), [
            'prefs' => [
                'core.configurable' => ['database' => '1'],
            ],
        ])
        ->assertRedirect(route('admin.account.notifications.show'));

    expect(NotificationPreference::where('user_id', $actor->id)->where('key', 'core.configurable')->where('channel', 'mail')->where('enabled', false)->exists())->toBeTrue()
        ->and(NotificationPreference::where('user_id', $actor->id)->where('channel', 'database')->exists())->toBeFalse();
});

it('only affects the acting user\'s own preferences', function () {
    config(['baobab.notifications.declarations' => [
        ['key' => 'core.configurable', 'channels' => ['database'], 'configurable' => true],
    ]]);

    $other = preferencesActor();
    $actor = preferencesActor();

    $this->actingAs($actor, 'baobab')
        ->post(route('admin.account.notifications.update'), [
            'prefs' => ['core.configurable' => []],
        ]);

    expect(NotificationPreference::where('user_id', $other->id)->exists())->toBeFalse();
});
