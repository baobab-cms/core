<?php

use Baobab\Access\Actions\GrantPermission;
use Baobab\Audit\Models\AuditEntry;
use Baobab\Users\Models\User;
use Spatie\Permission\Models\Role;

it('denies the role fiche without baobab.access.manage', function () {
    $user = User::create(['name' => 'Nobody', 'email' => 'nobody-fiche@example.com', 'password' => 'secret']);
    app(GrantPermission::class)($user, 'baobab.admin.access');

    $role = Role::findByName('editor', 'baobab');

    $this->actingAs($user, 'baobab')
        ->get("/admin/access/{$role->id}")
        ->assertForbidden();
});

it('renders the role fiche with level, permissions and holders', function () {
    $manager = User::create(['name' => 'Manager', 'email' => 'manager-fiche@example.com', 'password' => 'secret']);
    app(GrantPermission::class)($manager, 'baobab.admin.access');
    app(GrantPermission::class)($manager, 'baobab.access.manage');

    $role = Role::findByName('editor', 'baobab');
    app(GrantPermission::class)($role, 'baobab.audit.view');

    $holder = User::create(['name' => 'Holder', 'email' => 'holder-fiche@example.com', 'password' => 'secret']);
    $holder->assignRole($role);

    $this->actingAs($manager, 'baobab')
        ->get("/admin/access/{$role->id}")
        ->assertOk()
        ->assertSee($role->name)
        ->assertSee((string) $role->level)
        ->assertSee('baobab.audit.view')
        ->assertSee('Holder')
        ->assertSee('holder-fiche@example.com');
});

it('updates the requires_two_factor setting from the role fiche and journalizes it', function () {
    $manager = User::create(['name' => 'Manager', 'email' => 'manager-fiche2@example.com', 'password' => 'secret']);
    $manager->assignRole(Role::findByName('admin', 'baobab'));
    app(GrantPermission::class)($manager, 'baobab.admin.access');
    app(GrantPermission::class)($manager, 'baobab.access.manage');

    $role = Role::findByName('editor', 'baobab');
    expect((bool) $role->requires_two_factor)->toBeFalse();

    $this->actingAs($manager, 'baobab')
        ->patch("/admin/access/{$role->id}/settings", ['requires_two_factor' => '1'])
        ->assertRedirect();

    expect((bool) $role->fresh()->requires_two_factor)->toBeTrue();
    expect(
        AuditEntry::where('action', 'role.updated')
            ->where('data->after->requires_two_factor', true)
            ->exists()
    )->toBeTrue();

    $this->actingAs($manager, 'baobab')
        ->patch("/admin/access/{$role->id}/settings", [])
        ->assertRedirect();

    expect((bool) $role->fresh()->requires_two_factor)->toBeFalse();
});

it('denies updating role settings without baobab.access.manage', function () {
    $user = User::create(['name' => 'Nobody', 'email' => 'nobody-fiche2@example.com', 'password' => 'secret']);
    app(GrantPermission::class)($user, 'baobab.admin.access');

    $role = Role::findByName('editor', 'baobab');

    $this->actingAs($user, 'baobab')
        ->patch("/admin/access/{$role->id}/settings", ['requires_two_factor' => '1'])
        ->assertForbidden();
});
