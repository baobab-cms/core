<?php

use Baobab\Access\Actions\GrantPermission;
use Baobab\Actions\Modules\ActivateModule;
use Baobab\Actions\Modules\InstallModule;
use Baobab\Admin\Sidebar\SidebarBuilder;
use Baobab\Admin\Sidebar\SidebarItem;
use Baobab\Facades\Hook;
use Baobab\Modules\Models\ModuleMenuItem;
use Baobab\Users\Models\User;

// ── Intégration via un vrai manifest (fixture acme/blog) ─────────────────────────

it('builds the sidebar from an active module\'s menus.admin entries', function () {
    config(['baobab.modules.paths' => ['local' => [fixtureModulesPath('local/*')]]]);
    app(InstallModule::class)('acme/blog');
    app(ActivateModule::class)('acme/blog');

    $user = User::create(['name' => 'Viewer', 'email' => 'viewer@example.com', 'password' => 'secret']);

    $sidebar = app(SidebarBuilder::class)->build($user);

    expect($sidebar)->toHaveCount(1);
    expect($sidebar->first()->label)->toBe('Blog');
    expect($sidebar->first()->children)->toHaveCount(1);
    expect($sidebar->first()->children[0]->label)->toBe('Articles');
});

it('excludes menu items belonging to an installed-but-inactive module', function () {
    config(['baobab.modules.paths' => ['local' => [fixtureModulesPath('local/*')]]]);
    app(InstallModule::class)('acme/blog');

    $user = User::create(['name' => 'Viewer', 'email' => 'viewer2@example.com', 'password' => 'secret']);

    $sidebar = app(SidebarBuilder::class)->build($user);

    expect($sidebar)->toBeEmpty();
});

// ── Filtrage par permission ───────────────────────────────────────────────────

it('filters out items the user lacks permission for, then shows them once granted', function () {
    $module = makeActiveModule();
    ModuleMenuItem::create([
        'module_id' => $module->id,
        'label' => 'Réglages',
        'route' => 'admin.acme.settings.index',
        'permission' => 'acme.settings.view',
        'order' => 10,
    ]);

    $user = User::create(['name' => 'Restricted', 'email' => 'restricted@example.com', 'password' => 'secret']);

    expect(app(SidebarBuilder::class)->build($user))->toBeEmpty();

    app(GrantPermission::class)($user, 'acme.settings.view');

    expect(app(SidebarBuilder::class)->build($user->fresh()))->toHaveCount(1);
});

it('drops a parent whose route is null once all its children are filtered out', function () {
    $module = makeActiveModule();
    $parent = ModuleMenuItem::create([
        'module_id' => $module->id,
        'label' => 'Section',
        'route' => null,
        'permission' => null,
        'order' => 10,
    ]);
    ModuleMenuItem::create([
        'module_id' => $module->id,
        'parent_id' => $parent->id,
        'label' => 'Enfant restreint',
        'route' => null,
        'permission' => 'acme.hidden.view',
        'order' => 10,
    ]);

    $user = User::create(['name' => 'Restricted', 'email' => 'restricted2@example.com', 'password' => 'secret']);

    expect(app(SidebarBuilder::class)->build($user))->toBeEmpty();
});

// ── Extension via le hook baobab.admin.menu ───────────────────────────────────

it('lets the baobab.admin.menu filter inject an extra sidebar item', function () {
    $user = User::create(['name' => 'Viewer', 'email' => 'viewer3@example.com', 'password' => 'secret']);

    Hook::modify('baobab.admin.menu', function ($items) {
        return $items->push(new SidebarItem(
            id: 999,
            label: 'Injecté',
            icon: null,
            url: null,
            order: 999,
        ));
    });

    $sidebar = app(SidebarBuilder::class)->build($user);

    expect($sidebar->pluck('label'))->toContain('Injecté');
});
