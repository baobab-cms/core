<?php

use Baobab\Access\Actions\GrantPermission;
use Baobab\Users\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    // JsonFileHealthResultStore écrit sur le disque `local` réel — sans
    // ce fake, un run précédent (même suite complète) laisse un
    // health.json qui pollue le test « jamais exécuté ».
    Storage::fake('local');
});

/**
 * @param  list<string>  $permissions
 */
function healthActor(array $permissions): User
{
    static $counter = 0;
    $counter++;

    $user = User::create([
        'name' => "Health Actor {$counter}",
        'email' => "health-actor-{$counter}@example.com",
        'password' => 'secret',
    ]);

    app(GrantPermission::class)($user, 'baobab.admin.access');

    foreach ($permissions as $permission) {
        app(GrantPermission::class)($user, $permission);
    }

    return $user;
}

it('denies the screen without baobab.system.health.view', function () {
    $this->actingAs(healthActor([]), 'baobab')
        ->get(route('admin.system.health.index'))
        ->assertForbidden();
});

it('shows an empty state when no check has ever run', function () {
    $this->actingAs(healthActor(['baobab.system.health.view']), 'baobab')
        ->get(route('admin.system.health.index'))
        ->assertOk()
        ->assertSee(__('baobab::admin.health.never_checked'));
});

it('refreshes the checks and shows the 9 results', function () {
    $this->actingAs(healthActor(['baobab.system.health.view']), 'baobab')
        ->post(route('admin.system.health.refresh'))
        ->assertRedirect(route('admin.system.health.index'))
        ->assertSessionHas('toast');

    $this->actingAs(healthActor(['baobab.system.health.view']), 'baobab')
        ->get(route('admin.system.health.index'))
        ->assertOk()
        ->assertSee('Base de données')
        ->assertSee('Espace disque')
        ->assertSee('HTTPS / debug');
});
