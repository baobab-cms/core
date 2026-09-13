<?php

use Baobab\Access\Actions\GrantDirectPermission;
use Baobab\Access\Actions\GrantPermission;
use Baobab\Access\Actions\RevokeDirectPermission;
use Baobab\Access\Exceptions\AdminLockoutException;
use Baobab\Access\Models\DirectPermissionGrant;
use Baobab\Audit\Models\AuditEntry;
use Baobab\Users\Models\User;
use Spatie\Permission\Models\Role;

// ── Actions ──────────────────────────────────────────────────────────────────

it('grants a direct permission with a justification and tracks it', function () {
    $target = User::create(['name' => 'Target', 'email' => 'target-directperm@example.com', 'password' => 'secret']);
    $grantedBy = User::create(['name' => 'Grantor', 'email' => 'grantor-directperm@example.com', 'password' => 'secret']);

    $grant = app(GrantDirectPermission::class)($target, 'baobab.audit.view', 'Ponctuel, support niveau 2.', $grantedBy);

    expect($target->hasPermissionTo('baobab.audit.view', 'baobab'))->toBeTrue()
        ->and($grant->justification)->toBe('Ponctuel, support niveau 2.')
        ->and($grant->granted_by)->toBe($grantedBy->id)
        ->and(DirectPermissionGrant::where('user_id', $target->id)->where('permission_id', $grant->permission_id)->exists())->toBeTrue()
        ->and(AuditEntry::where('action', 'direct_permission.granted')->where('data->justification', 'Ponctuel, support niveau 2.')->exists())->toBeTrue();
});

it('refuses to grant a direct permission without a justification', function () {
    $target = User::create(['name' => 'Target', 'email' => 'target-directperm2@example.com', 'password' => 'secret']);

    expect(fn () => app(GrantDirectPermission::class)($target, 'baobab.audit.view', '   ', null))
        ->toThrow(InvalidArgumentException::class);

    expect($target->hasPermissionTo('baobab.audit.view', 'baobab'))->toBeFalse();
});

it('revokes a direct permission and removes it from the tracking table', function () {
    $target = User::create(['name' => 'Target', 'email' => 'target-directperm3@example.com', 'password' => 'secret']);
    $grant = app(GrantDirectPermission::class)($target, 'baobab.audit.view', 'Justification.', null);

    app(RevokeDirectPermission::class)($target, 'baobab.audit.view');

    expect($target->fresh()->hasPermissionTo('baobab.audit.view', 'baobab'))->toBeFalse()
        ->and(DirectPermissionGrant::whereKey($grant->id)->exists())->toBeFalse();
});

it('still refuses to revoke baobab.admin.access from a user directly, tracking table untouched', function () {
    $target = User::create(['name' => 'Target', 'email' => 'target-directperm4@example.com', 'password' => 'secret']);
    app(GrantPermission::class)($target, 'baobab.admin.access');

    expect(fn () => app(RevokeDirectPermission::class)($target, 'baobab.admin.access'))
        ->toThrow(AdminLockoutException::class);

    expect($target->fresh()->hasPermissionTo('baobab.admin.access', 'baobab'))->toBeTrue();
});

// ── Écrans ───────────────────────────────────────────────────────────────────

it('denies granting a direct permission without baobab.access.manage', function () {
    $actor = User::create(['name' => 'Actor', 'email' => 'actor-directperm@example.com', 'password' => 'secret']);
    app(GrantPermission::class)($actor, 'baobab.admin.access');

    $target = User::create(['name' => 'Target', 'email' => 'target-directperm5@example.com', 'password' => 'secret']);

    $this->actingAs($actor, 'baobab')
        ->post("/admin/users/{$target->id}/permissions", ['permission' => 'baobab.audit.view', 'justification' => 'Test.'])
        ->assertForbidden();
});

it('grants a direct permission from the user fiche over HTTP', function () {
    $actor = User::create(['name' => 'Actor', 'email' => 'actor-directperm2@example.com', 'password' => 'secret']);
    app(GrantPermission::class)($actor, 'baobab.admin.access');
    app(GrantPermission::class)($actor, 'baobab.access.manage');
    app(GrantPermission::class)($actor, 'baobab.users.impersonate');

    $target = User::create(['name' => 'Target', 'email' => 'target-directperm6@example.com', 'password' => 'secret']);

    $this->actingAs($actor, 'baobab')
        ->post("/admin/users/{$target->id}/permissions", ['permission' => 'baobab.audit.view', 'justification' => 'Support ponctuel.'])
        ->assertRedirect();

    expect($target->fresh()->hasPermissionTo('baobab.audit.view', 'baobab'))->toBeTrue();

    $this->actingAs($actor, 'baobab')
        ->get("/admin/users/{$target->id}")
        ->assertOk()
        ->assertSee('baobab.audit.view')
        ->assertSee('Support ponctuel.');
});

it('rejects granting a direct permission without a justification over HTTP', function () {
    $actor = User::create(['name' => 'Actor', 'email' => 'actor-directperm3@example.com', 'password' => 'secret']);
    app(GrantPermission::class)($actor, 'baobab.admin.access');
    app(GrantPermission::class)($actor, 'baobab.access.manage');

    $target = User::create(['name' => 'Target', 'email' => 'target-directperm7@example.com', 'password' => 'secret']);

    $this->actingAs($actor, 'baobab')
        ->post("/admin/users/{$target->id}/permissions", ['permission' => 'baobab.audit.view', 'justification' => ''])
        ->assertSessionHasErrors('justification');
});

it('revokes a direct permission from the user fiche over HTTP', function () {
    $actor = User::create(['name' => 'Actor', 'email' => 'actor-directperm4@example.com', 'password' => 'secret']);
    app(GrantPermission::class)($actor, 'baobab.admin.access');
    app(GrantPermission::class)($actor, 'baobab.access.manage');

    $target = User::create(['name' => 'Target', 'email' => 'target-directperm8@example.com', 'password' => 'secret']);
    app(GrantDirectPermission::class)($target, 'baobab.audit.view', 'Justification.', $actor);

    $this->actingAs($actor, 'baobab')
        ->delete("/admin/users/{$target->id}/permissions/baobab.audit.view")
        ->assertRedirect();

    expect($target->fresh()->hasPermissionTo('baobab.audit.view', 'baobab'))->toBeFalse();
});

it('denies the direct permissions review screen without baobab.access.manage', function () {
    $actor = User::create(['name' => 'Actor', 'email' => 'actor-directperm5@example.com', 'password' => 'secret']);
    app(GrantPermission::class)($actor, 'baobab.admin.access');

    $this->actingAs($actor, 'baobab')
        ->get('/admin/access/direct-permissions')
        ->assertForbidden();
});

it('lists all direct permission exceptions on the review screen', function () {
    $actor = User::create(['name' => 'Actor', 'email' => 'actor-directperm6@example.com', 'password' => 'secret']);
    $actor->assignRole(Role::findByName('admin', 'baobab'));
    app(GrantPermission::class)($actor, 'baobab.admin.access');
    app(GrantPermission::class)($actor, 'baobab.access.manage');

    $target = User::create(['name' => 'Reviewed User', 'email' => 'target-directperm9@example.com', 'password' => 'secret']);
    app(GrantDirectPermission::class)($target, 'baobab.audit.view', 'Raison consignée.', $actor);

    $this->actingAs($actor, 'baobab')
        ->get('/admin/access/direct-permissions')
        ->assertOk()
        ->assertSee('Reviewed User')
        ->assertSee('baobab.audit.view')
        ->assertSee('Raison consignée.');
});
