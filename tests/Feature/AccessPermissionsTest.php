<?php

use Baobab\Access\Actions\CreateRole;
use Baobab\Access\Actions\DeleteRole;
use Baobab\Access\Actions\RevokePermission;
use Baobab\Access\Exceptions\AdminLockoutException;
use Baobab\Access\Exceptions\ProtectedRoleException;
use Baobab\Actions\Modules\ActivateModule;
use Baobab\Actions\Modules\DeactivateModule;
use Baobab\Actions\Modules\InstallModule;
use Baobab\Actions\Modules\UninstallModule;
use Baobab\Facades\Hook;
use Baobab\Users\Models\User;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

// ── Rôles par défaut ──────────────────────────────────────────────────────────

it('seeds the six default roles after migration', function () {
    $expected = ['super-admin', 'admin', 'editor', 'moderator', 'author', 'visitor'];

    foreach ($expected as $name) {
        expect(Role::where('name', $name)->where('guard_name', 'baobab')->exists())->toBeTrue();
    }
});

it('default roles have the correct level values', function () {
    $levels = Role::where('guard_name', 'baobab')->pluck('level', 'name');

    expect($levels['super-admin'])->toBe(100)
        ->and($levels['admin'])->toBe(80)
        ->and($levels['editor'])->toBe(60)
        ->and($levels['moderator'])->toBe(40)
        ->and($levels['author'])->toBe(40)
        ->and($levels['visitor'])->toBe(10);
});

// ── Gate::before (super-admin bypass) ────────────────────────────────────────

it('super-admin passes all Gate checks', function () {
    $user = User::create([
        'name' => 'Boss',
        'email' => 'boss@example.com',
        'password' => 'secret',
    ]);
    $user->assignRole(Role::findByName('super-admin', 'baobab'));

    Gate::define('some.arbitrary.permission', fn () => false);

    expect(Gate::forUser($user)->allows('some.arbitrary.permission'))->toBeTrue();
});

it('non-super-admin does not bypass Gate checks', function () {
    $user = User::create([
        'name' => 'Editor',
        'email' => 'editor@example.com',
        'password' => 'secret',
    ]);
    $user->assignRole(Role::findByName('editor', 'baobab'));

    Gate::define('super.only', fn () => false);

    expect(Gate::forUser($user)->allows('super.only'))->toBeFalse();
});

// ── CreateRole ────────────────────────────────────────────────────────────────

it('CreateRole emits baobab.access.role.created hook', function () {
    $fired = false;
    Hook::listen('baobab.access.role.created', function () use (&$fired): void {
        $fired = true;
    });

    app(CreateRole::class)('custom-role', 50);

    expect($fired)->toBeTrue();
    expect(Role::where('name', 'custom-role')->where('guard_name', 'baobab')->exists())->toBeTrue();
});

// ── DeleteRole ────────────────────────────────────────────────────────────────

it('DeleteRole refuses to delete super-admin', function () {
    $role = Role::findByName('super-admin', 'baobab');

    expect(fn () => app(DeleteRole::class)($role))
        ->toThrow(ProtectedRoleException::class);
});

it('DeleteRole emits baobab.access.role.deleted hook for other roles', function () {
    $role = app(CreateRole::class)('deletable', 20);
    $fired = false;
    Hook::listen('baobab.access.role.deleted', function () use (&$fired): void {
        $fired = true;
    });

    app(DeleteRole::class)($role);

    expect($fired)->toBeTrue();
});

// ── RevokePermission ──────────────────────────────────────────────────────────

it('RevokePermission refuses to revoke baobab.admin.access from a user directly', function () {
    $user = User::create([
        'name' => 'Locked',
        'email' => 'locked@example.com',
        'password' => 'secret',
    ]);

    expect(fn () => app(RevokePermission::class)($user, 'baobab.admin.access'))
        ->toThrow(AdminLockoutException::class);
});

// ── ActivateModule crée les permissions Spatie ────────────────────────────────

it('ActivateModule creates Spatie permissions declared in the module manifest', function () {
    config(['baobab.modules.paths' => [
        'local' => [fixtureModulesPath('local/*')],
    ]]);

    app(InstallModule::class)('acme/blog');
    app(ActivateModule::class)('acme/blog');

    expect(Permission::where('guard_name', 'baobab')->whereIn('name', [
        'acme.blog.posts.view',
        'acme.blog.posts.create',
    ])->count())->toBe(2);
});

// ── UninstallModule --purge supprime les permissions Spatie ───────────────────

it('UninstallModule --purge removes the module Spatie permissions', function () {
    config(['baobab.modules.paths' => [
        'local' => [fixtureModulesPath('local/*')],
    ]]);

    app(InstallModule::class)('acme/blog');
    app(ActivateModule::class)('acme/blog');

    // Deactivate first, then uninstall with purge
    DeactivateModule::class;
    app(DeactivateModule::class)('acme/blog');
    app(UninstallModule::class)('acme/blog', purge: true);

    expect(Permission::where('guard_name', 'baobab')->whereIn('name', [
        'acme.blog.posts.view',
        'acme.blog.posts.create',
    ])->count())->toBe(0);
});
