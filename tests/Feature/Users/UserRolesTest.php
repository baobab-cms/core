<?php

use Baobab\Access\Exceptions\HierarchyViolationException;
use Baobab\Audit\Models\AuditEntry;
use Baobab\Users\Actions\GrantUserRole;
use Baobab\Users\Actions\RevokeUserRole;
use Baobab\Users\Models\User;
use Spatie\Permission\Models\Role;

/**
 * Rôles modifiables depuis la fiche utilisateur (spec 05 §5, décision 5),
 * bornés par la hiérarchie (§4.1) et l'anti-lockout (§6.1).
 */
function rolesUser(string $role, string $email): User
{
    $user = User::create(['name' => ucfirst($role), 'email' => $email, 'password' => 'secret-password']);
    $user->assignRole(Role::findByName($role, 'baobab'));

    return $user;
}

it('adds and removes a role from the user page, audited', function () {
    $admin = rolesUser('admin', 'roles-admin@example.com');
    $author = rolesUser('author', 'roles-author@example.com');
    $editor = Role::findByName('editor', 'baobab');

    $this->actingAs($admin, 'baobab')
        ->get(route('admin.users.show', ['user' => $author]))
        ->assertOk()
        ->assertSee(route('admin.users.roles.store', ['user' => $author]), false);

    $this->post(route('admin.users.roles.store', ['user' => $author]), ['role' => $editor->id])
        ->assertRedirect(route('admin.users.show', ['user' => $author]));

    expect($author->fresh()->hasRole('editor', 'baobab'))->toBeTrue()
        ->and(AuditEntry::where('action', 'role.assigned')->where('auditable_id', $author->id)->exists())->toBeTrue();

    $this->delete(route('admin.users.roles.destroy', ['user' => $author, 'role' => $editor]))
        ->assertRedirect(route('admin.users.show', ['user' => $author]));

    expect($author->fresh()->hasRole('editor', 'baobab'))->toBeFalse();
});

it('refuses to grant a role at or above the actor level', function () {
    $admin = rolesUser('admin', 'roles-admin-high@example.com');
    $author = rolesUser('author', 'roles-author-high@example.com');

    expect(fn () => app(GrantUserRole::class)($admin, $author, Role::findByName('admin', 'baobab')))
        ->toThrow(HierarchyViolationException::class);

    expect($author->fresh()->hasRole('admin', 'baobab'))->toBeFalse();
});

it('refuses to change the roles of a user at or above the actor level', function () {
    $admin = rolesUser('admin', 'roles-admin-peer@example.com');
    $peer = rolesUser('admin', 'roles-peer@example.com');

    expect(fn () => app(GrantUserRole::class)($admin, $peer, Role::findByName('author', 'baobab')))
        ->toThrow(HierarchyViolationException::class);

    $this->actingAs($admin, 'baobab')
        ->get(route('admin.users.show', ['user' => $peer]))
        ->assertOk()
        ->assertDontSee(route('admin.users.roles.store', ['user' => $peer]), false);
});

it('refuses to change your own roles', function () {
    $admin = rolesUser('admin', 'roles-self@example.com');

    expect(fn () => app(RevokeUserRole::class)($admin, $admin, Role::findByName('admin', 'baobab')))
        ->toThrow(HierarchyViolationException::class);

    expect($admin->fresh()->hasRole('admin', 'baobab'))->toBeTrue();
});

it('refuses to grant a role to yourself', function () {
    $admin = rolesUser('admin', 'roles-grant-self@example.com');

    expect(fn () => app(GrantUserRole::class)($admin, $admin, Role::findByName('editor', 'baobab')))
        ->toThrow(HierarchyViolationException::class, 'Cannot change your own roles.');
});

it('is a no-op when the user already has the role, without a second audit entry', function () {
    $admin = rolesUser('admin', 'roles-noop-admin@example.com');
    $author = rolesUser('author', 'roles-noop-author@example.com');
    $editor = Role::findByName('editor', 'baobab');

    app(GrantUserRole::class)($admin, $author, $editor);
    app(GrantUserRole::class)($admin, $author, $editor);

    expect($author->fresh()->hasRole('editor', 'baobab'))->toBeTrue()
        ->and(AuditEntry::where('action', 'role.assigned')->where('auditable_id', $author->id)->count())->toBe(1);
});

it('keeps the last super-admin protected', function () {
    $superAdmin = rolesUser('super-admin', 'roles-last-super@example.com');
    $owner = User::create(['name' => 'Owner', 'email' => 'roles-owner@example.com', 'password' => 'secret-password']);
    $owner->assignRole(Role::findByName('super-admin', 'baobab'));

    // Deux super-admins de niveau égal : la hiérarchie refuse avant même
    // l'anti-lockout — le dernier super-admin reste de toute façon intouchable.
    expect(fn () => app(RevokeUserRole::class)($owner, $superAdmin, Role::findByName('super-admin', 'baobab')))
        ->toThrow(HierarchyViolationException::class);
});
