<?php

use Baobab\Access\Actions\GrantPermission;
use Baobab\Admin\Access\PermissionMatrixBuilder;
use Baobab\Audit\Models\AuditEntry;
use Baobab\Modules\Models\Module;
use Baobab\Modules\Models\ModulePermission;
use Baobab\Users\Models\User;
use Spatie\Permission\Models\Role;

// ── PermissionMatrixBuilder ───────────────────────────────────────────────────

it('groups permissions by their declaring module and ungrouped ones under Core', function () {
    $module = makeActiveModule('acme/blog');
    ModulePermission::create([
        'module_id' => $module->id,
        'key' => 'acme.blog.posts.view',
        'label' => 'Voir les articles',
    ]);

    $role = Role::findByName('editor', 'baobab');
    app(GrantPermission::class)($role, 'acme.blog.posts.view');
    app(GrantPermission::class)($role, 'baobab.admin.access');

    $matrix = app(PermissionMatrixBuilder::class)->build();

    $moduleGroup = $matrix['groups']->firstWhere('label', 'acme/blog');
    expect($moduleGroup)->not->toBeNull()
        ->and($moduleGroup['active'])->toBeTrue()
        ->and($moduleGroup['permissions']->pluck('name'))->toContain('acme.blog.posts.view');

    $coreGroup = $matrix['groups']->firstWhere('label', 'Core');
    expect($coreGroup)->not->toBeNull()
        ->and($coreGroup['permissions']->pluck('name'))->toContain('baobab.admin.access');
});

it('flags a group as inactive when its declaring module is inactive', function () {
    $module = Module::create([
        'name' => 'acme/shop',
        'title' => 'acme/shop',
        'type' => 'module',
        'version' => '1.0.0',
        'provider' => 'Acme\\Shop\\Providers\\ShopServiceProvider',
        'source' => 'local',
        'path' => '/tmp/acme-shop',
        'manifest' => [],
        'status' => 'inactive',
    ]);
    ModulePermission::create([
        'module_id' => $module->id,
        'key' => 'acme.shop.orders.view',
        'label' => 'Voir les commandes',
    ]);
    app(GrantPermission::class)(Role::findByName('editor', 'baobab'), 'acme.shop.orders.view');

    $matrix = app(PermissionMatrixBuilder::class)->build();
    $group = $matrix['groups']->firstWhere('label', 'acme/shop');

    expect($group['active'])->toBeFalse();
});

// ── Écran ──────────────────────────────────────────────────────────────────────

it('denies the access screen without baobab.access.manage', function () {
    $user = User::create(['name' => 'Nobody', 'email' => 'nobody@example.com', 'password' => 'secret']);
    app(GrantPermission::class)($user, 'baobab.admin.access');

    $this->actingAs($user, 'baobab')
        ->get('/admin/access')
        ->assertForbidden();
});

it('renders the matrix with roles as columns', function () {
    $user = User::create(['name' => 'Manager', 'email' => 'manager@example.com', 'password' => 'secret']);
    app(GrantPermission::class)($user, 'baobab.admin.access');
    app(GrantPermission::class)($user, 'baobab.access.manage');

    $this->actingAs($user, 'baobab')
        ->get('/admin/access')
        ->assertOk()
        ->assertSee('editor')
        ->assertSee('baobab.access.manage');
});

it('toggles a permission for a role via POST and produces an audit entry', function () {
    $user = User::create(['name' => 'Manager', 'email' => 'manager2@example.com', 'password' => 'secret']);
    app(GrantPermission::class)($user, 'baobab.admin.access');
    app(GrantPermission::class)($user, 'baobab.access.manage');

    $role = Role::findByName('editor', 'baobab');
    expect($role->hasPermissionTo('baobab.audit.view', 'baobab'))->toBeFalse();

    $this->actingAs($user, 'baobab')
        ->post("/admin/access/{$role->id}/permissions/baobab.audit.view")
        ->assertRedirect();

    expect($role->fresh()->hasPermissionTo('baobab.audit.view', 'baobab'))->toBeTrue();
    expect(
        AuditEntry::where('action', 'permission.granted')->where('data->permission', 'baobab.audit.view')->exists()
    )->toBeTrue();

    $this->actingAs($user, 'baobab')
        ->post("/admin/access/{$role->id}/permissions/baobab.audit.view")
        ->assertRedirect();

    expect($role->fresh()->hasPermissionTo('baobab.audit.view', 'baobab'))->toBeFalse();
    expect(
        AuditEntry::where('action', 'permission.revoked')->where('data->permission', 'baobab.audit.view')->exists()
    )->toBeTrue();
});

it('creates a role via the matrix screen and journalizes it', function () {
    $user = User::create(['name' => 'Manager', 'email' => 'manager4@example.com', 'password' => 'secret']);
    app(GrantPermission::class)($user, 'baobab.admin.access');
    app(GrantPermission::class)($user, 'baobab.access.manage');

    $this->actingAs($user, 'baobab')
        ->post('/admin/access/roles', ['name' => 'journalist', 'level' => 30])
        ->assertRedirect();

    expect(Role::where('name', 'journalist')->where('guard_name', 'baobab')->where('level', 30)->exists())->toBeTrue();
    expect(AuditEntry::where('action', 'role.created')->where('data->name', 'journalist')->exists())->toBeTrue();
});

it('rejects creating a role with a duplicate name', function () {
    $user = User::create(['name' => 'Manager', 'email' => 'manager5@example.com', 'password' => 'secret']);
    app(GrantPermission::class)($user, 'baobab.admin.access');
    app(GrantPermission::class)($user, 'baobab.access.manage');

    $this->actingAs($user, 'baobab')
        ->post('/admin/access/roles', ['name' => 'editor', 'level' => 30])
        ->assertSessionHasErrors('name');
});

it('denies creating a role without baobab.access.manage', function () {
    $user = User::create(['name' => 'Nobody', 'email' => 'nobody2@example.com', 'password' => 'secret']);
    app(GrantPermission::class)($user, 'baobab.admin.access');

    $this->actingAs($user, 'baobab')
        ->post('/admin/access/roles', ['name' => 'journalist', 'level' => 30])
        ->assertForbidden();
});

it('refuses to toggle a permission on the super-admin role', function () {
    $user = User::create(['name' => 'Manager', 'email' => 'manager3@example.com', 'password' => 'secret']);
    app(GrantPermission::class)($user, 'baobab.admin.access');
    app(GrantPermission::class)($user, 'baobab.access.manage');

    $superAdmin = Role::findByName('super-admin', 'baobab');

    $this->actingAs($user, 'baobab')
        ->post("/admin/access/{$superAdmin->id}/permissions/baobab.audit.view")
        ->assertForbidden();
});
