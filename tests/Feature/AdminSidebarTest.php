<?php

use Baobab\Access\Actions\GrantPermission;
use Baobab\Actions\Modules\ActivateModule;
use Baobab\Actions\Modules\InstallModule;
use Baobab\Admin\Sidebar\SidebarBuilder;
use Baobab\Admin\Sidebar\SidebarItem;
use Baobab\Facades\Hook;
use Baobab\Modules\Models\ModuleMenuItem;
use Baobab\Users\Models\User;
use Illuminate\Http\Request;

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

// ── route_params ──────────────────────────────────────────────────────────────

it('resolves a parameterized route for a menu item declaring route_params', function () {
    $module = makeActiveModule();
    ModuleMenuItem::create([
        'module_id' => $module->id,
        'label' => 'Voitures',
        'route' => 'admin.content.index',
        'route_params' => ['contentType' => 'cars'],
        'permission' => null,
        'order' => 10,
    ]);

    $user = User::create(['name' => 'Viewer', 'email' => 'viewer4@example.com', 'password' => 'secret']);

    $sidebar = app(SidebarBuilder::class)->build($user);

    expect($sidebar)->toHaveCount(1)
        ->and($sidebar->first()->url)->toBe(route('admin.content.index', ['contentType' => 'cars']));
});

// ── Active state (aria-current, suivi n° 307 constat 7) ───────────────────────

it('marks the sidebar item whose URL matches the current request path as active', function () {
    $user = User::create(['name' => 'Viewer', 'email' => 'viewer6@example.com', 'password' => 'secret']);
    app(GrantPermission::class)($user, 'baobab.access.manage');

    app()->instance('request', Request::create(route('admin.access.index')));

    $sidebar = app(SidebarBuilder::class)->build($user->fresh());
    $item = $sidebar->firstWhere('label', __('baobab::admin.sidebar.access'));

    expect($item)->not->toBeNull()
        ->and($item->isActive)->toBeTrue();
});

it('does not mark a sidebar item as active when the current path does not match its URL', function () {
    $user = User::create(['name' => 'Viewer', 'email' => 'viewer7@example.com', 'password' => 'secret']);
    app(GrantPermission::class)($user, 'baobab.access.manage');

    app()->instance('request', Request::create('/admin/somewhere-else'));

    $sidebar = app(SidebarBuilder::class)->build($user->fresh());
    $item = $sidebar->firstWhere('label', __('baobab::admin.sidebar.access'));

    expect($item)->not->toBeNull()
        ->and($item->isActive)->toBeFalse();
});

// ── Extension via le hook baobab.admin.menu ───────────────────────────────────

// ── Éléments Core (registerCoreSidebarItems) ──────────────────────────────────

it('shows a Core-provided sidebar item once the user holds its permission', function () {
    $user = User::create(['name' => 'Admin viewer', 'email' => 'admin-viewer@example.com', 'password' => 'secret']);

    expect(app(SidebarBuilder::class)->build($user)->pluck('label'))->not->toContain(__('baobab::admin.sidebar.access'));

    app(GrantPermission::class)($user, 'baobab.access.manage');

    $sidebar = app(SidebarBuilder::class)->build($user->fresh());

    expect($sidebar->pluck('label'))->toContain(__('baobab::admin.sidebar.access'));
});

it('shows the Studio sidebar item once the user holds baobab.system.studio.manage', function () {
    $user = User::create(['name' => 'Studio viewer', 'email' => 'studio-viewer@example.com', 'password' => 'secret']);

    expect(app(SidebarBuilder::class)->build($user)->pluck('label'))->not->toContain(__('baobab::admin.sidebar.studio'));

    app(GrantPermission::class)($user, 'baobab.system.studio.manage');

    $sidebar = app(SidebarBuilder::class)->build($user->fresh());

    expect($sidebar->pluck('label'))->toContain(__('baobab::admin.sidebar.studio'));
});

it('threads a module menu item\'s icon through to the resulting SidebarItem', function () {
    $module = makeActiveModule();
    ModuleMenuItem::create([
        'module_id' => $module->id,
        'label' => 'Voitures',
        'icon' => 'bi-car-front',
        'route' => 'admin.content.index',
        'route_params' => ['contentType' => 'cars'],
        'permission' => null,
        'order' => 10,
    ]);

    $user = User::create(['name' => 'Viewer', 'email' => 'viewer5@example.com', 'password' => 'secret']);

    $sidebar = app(SidebarBuilder::class)->build($user);

    expect($sidebar->first()->icon)->toBe('bi-car-front');
});

it('assigns a non-null, allow-listed icon to every Core sidebar item', function () {
    $user = User::create(['name' => 'Super', 'email' => 'super-icons@example.com', 'password' => 'secret']);

    foreach ([
        'baobab.access.manage',
        'baobab.audit.view',
        'baobab.users.impersonate',
        'baobab.media.view',
        'baobab.system.branding.manage',
        'baobab.system.themes.manage',
        'baobab.menus.manage',
        'baobab.widgets.manage',
        'baobab.system.reading.manage',
        'baobab.system.seo.manage',
        'baobab.system.redirects.manage',
        'baobab.system.api.manage',
        'baobab.system.webhooks.manage',
        'baobab.system.search.manage',
        'baobab.system.studio.manage',
    ] as $permission) {
        app(GrantPermission::class)($user, $permission);
    }

    $sidebar = app(SidebarBuilder::class)->build($user->fresh());

    expect($sidebar)->not->toBeEmpty();

    foreach ($sidebar as $item) {
        expect($item->icon)->not->toBeNull()
            ->and($item->icon)->toMatch('/^(bi|fas|far|fab)-[a-z0-9-]+$/');
    }
});

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
