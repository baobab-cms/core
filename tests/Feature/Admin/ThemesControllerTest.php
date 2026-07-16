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

it('redirects to a signed preview link', function () {
    app(InstallModule::class)('acme/theme');
    $theme = Module::where('name', 'acme/theme')->firstOrFail();
    $actor = themesActor(['baobab.system.themes.manage']);

    $response = $this->actingAs($actor, 'baobab')
        ->get(route('admin.themes.preview', ['theme' => $theme->id]));

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('/theme-preview/');
});
