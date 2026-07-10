<?php

use Baobab\Access\Actions\CreateRole;
use Baobab\Access\Actions\DeleteRole;
use Baobab\Access\Actions\UpdateRole;
use Baobab\Access\Exceptions\HierarchyViolationException;
use Spatie\Permission\Models\Role;

// ── CreateRole ────────────────────────────────────────────────────────────────

it('CreateRole refuses a level equal to or above the actor\'s level', function () {
    actingAsLevel('moderator');

    expect(fn () => app(CreateRole::class)('too-high', 40))
        ->toThrow(HierarchyViolationException::class);

    expect(fn () => app(CreateRole::class)('too-high-2', 50))
        ->toThrow(HierarchyViolationException::class);
});

it('CreateRole allows a level strictly below the actor\'s level', function () {
    actingAsLevel('admin');

    $role = app(CreateRole::class)('junior-editor', 50);

    expect($role->name)->toBe('junior-editor');
});

it('CreateRole is unrestricted without an authenticated actor', function () {
    $role = app(CreateRole::class)('system-role', 99);

    expect($role->name)->toBe('system-role');
});

// ── UpdateRole ────────────────────────────────────────────────────────────────

it('UpdateRole refuses to touch a role whose current level is not strictly below the actor\'s', function () {
    $role = Role::create(['name' => 'peer', 'guard_name' => 'baobab', 'level' => 40]);
    actingAsLevel('moderator');

    expect(fn () => app(UpdateRole::class)($role, ['level' => 20]))
        ->toThrow(HierarchyViolationException::class);
});

it('UpdateRole refuses to raise a role to a level equal to or above the actor\'s', function () {
    $role = Role::create(['name' => 'junior', 'guard_name' => 'baobab', 'level' => 20]);
    actingAsLevel('moderator');

    expect(fn () => app(UpdateRole::class)($role, ['level' => 40]))
        ->toThrow(HierarchyViolationException::class);
});

it('UpdateRole allows changes within the actor\'s dominance', function () {
    $role = Role::create(['name' => 'junior', 'guard_name' => 'baobab', 'level' => 20]);
    actingAsLevel('admin');

    $updated = app(UpdateRole::class)($role, ['level' => 50]);

    expect($updated->getAttribute('level'))->toBe(50);
});

// ── DeleteRole ────────────────────────────────────────────────────────────────

it('DeleteRole refuses to delete a role whose level is not strictly below the actor\'s', function () {
    $role = Role::create(['name' => 'peer', 'guard_name' => 'baobab', 'level' => 40]);
    actingAsLevel('moderator');

    expect(fn () => app(DeleteRole::class)($role))
        ->toThrow(HierarchyViolationException::class);
});

it('DeleteRole allows deleting a role strictly below the actor\'s level', function () {
    $role = Role::create(['name' => 'junior', 'guard_name' => 'baobab', 'level' => 20]);
    actingAsLevel('admin');

    app(DeleteRole::class)($role);

    expect(Role::where('name', 'junior')->exists())->toBeFalse();
});
