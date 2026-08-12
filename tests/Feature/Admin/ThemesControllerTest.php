<?php

use Baobab\Access\Actions\GrantPermission;
use Baobab\Actions\Modules\InstallModule;
use Baobab\Modules\Models\Module;
use Baobab\Users\Models\User;

/**
 * @param  list<string>  $permissions
 */
function themesActor(array $permissions): User
{
    static $counter = 0;
    $counter++;

    $user = User::create([
        'name' => "Themes Actor {$counter}",
        'email' => "themes-actor-{$counter}@example.com",
        'password' => 'secret',
    ]);

    app(GrantPermission::class)($user, 'baobab.admin.access');

    foreach ($permissions as $permission) {
        app(GrantPermission::class)($user, $permission);
    }

    return $user;
}

beforeEach(function () {
    config(['baobab.modules.paths' => ['local' => [fixtureModulesPath('local/*')]]]);
});

it('denies the themes screen without baobab.system.themes.manage', function () {
    $actor = themesActor([]);

    $this->actingAs($actor, 'baobab')
        ->get(route('admin.themes.index'))
        ->assertForbidden();
});

it('lists installed themes to an actor with baobab.system.themes.manage', function () {
    app(InstallModule::class)('acme/theme');
    $actor = themesActor(['baobab.system.themes.manage']);

    $this->actingAs($actor, 'baobab')
        ->get(route('admin.themes.index'))
        ->assertOk()
        ->assertSee('Acme Theme');
});

it('activates an installed theme over HTTP', function () {
    app(InstallModule::class)('acme/theme');
    $theme = Module::where('name', 'acme/theme')->firstOrFail();
    $actor = themesActor(['baobab.system.themes.manage']);

    $this->actingAs($actor, 'baobab')
        ->post(route('admin.themes.activate', ['theme' => $theme->id]))
        ->assertRedirect(route('admin.themes.index'))
        ->assertSessionHas('toast');

    expect(Module::where('id', $theme->id)->value('status'))->toBe('active');
});

it('deactivates the active theme over HTTP and offers the gesture only on it', function () {
    app(InstallModule::class)('acme/theme');
    $theme = Module::where('name', 'acme/theme')->firstOrFail();
    $actor = themesActor(['baobab.system.themes.manage']);
    $this->actingAs($actor, 'baobab')->post(route('admin.themes.activate', ['theme' => $theme->id]));

    $this->actingAs($actor, 'baobab')
        ->get(route('admin.themes.index'))
        ->assertOk()
        ->assertSee(route('admin.themes.deactivate', ['theme' => $theme->id]), false);

    $this->actingAs($actor, 'baobab')
        ->post(route('admin.themes.deactivate', ['theme' => $theme->id]))
        ->assertRedirect(route('admin.themes.index'))
        ->assertSessionHas('toast');

    expect(Module::where('id', $theme->id)->value('status'))->toBe('inactive');

    // Le geste disparaît une fois le thème inactif : l'écran ne propose plus
    // que de l'activer ou de le prévisualiser.
    $this->actingAs($actor, 'baobab')
        ->get(route('admin.themes.index'))
        ->assertOk()
        ->assertDontSee(route('admin.themes.deactivate', ['theme' => $theme->id]), false);

    removeThemeLink('acme-theme');
});

it('denies deactivating a theme without baobab.system.themes.manage', function () {
    app(InstallModule::class)('acme/theme');
    $theme = Module::where('name', 'acme/theme')->firstOrFail();

    $this->actingAs(themesActor([]), 'baobab')
        ->post(route('admin.themes.deactivate', ['theme' => $theme->id]))
        ->assertForbidden();
});

it('redirects to a signed preview link', function () {
    app(InstallModule::class)('acme/theme');
    $theme = Module::where('name', 'acme/theme')->firstOrFail();
    $actor = themesActor(['baobab.system.themes.manage']);

    $response = $this->actingAs($actor, 'baobab')
        ->get(route('admin.themes.preview', ['theme' => $theme->id]));

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('/theme-preview/');
});
