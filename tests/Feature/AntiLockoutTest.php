<?php

use Baobab\Access\Actions\AssignRole;
use Baobab\Access\Actions\GrantPermission;
use Baobab\Access\Actions\RemoveRole;
use Baobab\Access\Exceptions\AdminLockoutException;
use Baobab\Facades\Hook;
use Baobab\Tests\TestCase;
use Baobab\Users\Models\User;
use Spatie\Permission\Models\Role;

// ── Dernier Super Admin ─────────────────────────────────────────────────────────

it('RemoveRole refuses to remove super-admin from the last super-admin', function () {
    $role = Role::findByName('super-admin', 'baobab');
    $user = User::create([
        'name' => 'Solo',
        'email' => 'solo@example.com',
        'password' => 'secret',
    ]);
    app(AssignRole::class)($user, $role);

    expect(fn () => app(RemoveRole::class)($user, $role))
        ->toThrow(AdminLockoutException::class);
});

it('RemoveRole allows removing super-admin when another super-admin remains', function () {
    $role = Role::findByName('super-admin', 'baobab');

    $first = User::create(['name' => 'First', 'email' => 'first@example.com', 'password' => 'secret']);
    $second = User::create(['name' => 'Second', 'email' => 'second@example.com', 'password' => 'secret']);

    app(AssignRole::class)($first, $role);
    app(AssignRole::class)($second, $role);

    app(RemoveRole::class)($first, $role);

    expect(User::findOrFail($first->id)->hasRole('super-admin', 'baobab'))->toBeFalse()
        ->and(User::findOrFail($second->id)->hasRole('super-admin', 'baobab'))->toBeTrue();
});

// ── Dernier rôle admin d'un utilisateur (self-lockout) ───────────────────────────

it('RemoveRole refuses to let a user remove their own last role granting admin access', function () {
    /** @var TestCase $this */
    $adminRole = Role::findByName('admin', 'baobab');
    app(GrantPermission::class)($adminRole, 'baobab.admin.access');

    $user = User::create(['name' => 'Manager', 'email' => 'manager@example.com', 'password' => 'secret']);
    app(AssignRole::class)($user, $adminRole);

    $this->actingAs($user, 'baobab');

    expect(fn () => app(RemoveRole::class)($user, $adminRole))
        ->toThrow(AdminLockoutException::class);
});

it('RemoveRole allows another actor to remove a user\'s last admin-granting role', function () {
    /** @var TestCase $this */
    $adminRole = Role::findByName('admin', 'baobab');
    app(GrantPermission::class)($adminRole, 'baobab.admin.access');

    $actor = User::create(['name' => 'Boss', 'email' => 'boss@example.com', 'password' => 'secret']);
    $target = User::create(['name' => 'Target', 'email' => 'target@example.com', 'password' => 'secret']);
    app(AssignRole::class)($target, $adminRole);

    $this->actingAs($actor, 'baobab');

    app(RemoveRole::class)($target, $adminRole);

    expect(User::findOrFail($target->id)->hasRole('admin', 'baobab'))->toBeFalse();
});

it('RemoveRole allows self-removal of a role that does not carry admin access', function () {
    /** @var TestCase $this */
    $adminRole = Role::findByName('admin', 'baobab');
    app(GrantPermission::class)($adminRole, 'baobab.admin.access');
    $editorRole = Role::findByName('editor', 'baobab');

    $user = User::create(['name' => 'Dual', 'email' => 'dual@example.com', 'password' => 'secret']);
    app(AssignRole::class)($user, $adminRole);
    app(AssignRole::class)($user, $editorRole);

    $this->actingAs($user, 'baobab');

    app(RemoveRole::class)($user, $editorRole);

    expect(User::findOrFail($user->id)->hasRole('editor', 'baobab'))->toBeFalse()
        ->and(User::findOrFail($user->id)->hasRole('admin', 'baobab'))->toBeTrue();
});

// ── Hooks ─────────────────────────────────────────────────────────────────────

it('AssignRole and RemoveRole emit hooks', function () {
    $role = Role::findByName('editor', 'baobab');
    $user = User::create(['name' => 'Hooked', 'email' => 'hooked@example.com', 'password' => 'secret']);

    $assigned = false;
    $removed = false;
    Hook::listen('baobab.access.role.assigned', function () use (&$assigned): void {
        $assigned = true;
    });
    Hook::listen('baobab.access.role.removed', function () use (&$removed): void {
        $removed = true;
    });

    app(AssignRole::class)($user, $role);
    app(RemoveRole::class)($user, $role);

    expect($assigned)->toBeTrue()
        ->and($removed)->toBeTrue();
});
