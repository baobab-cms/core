<?php

use Spatie\Permission\Models\Role;

it('grants baobab.admin.access by default to the four agency roles', function () {
    foreach (['admin', 'editor', 'moderator', 'author'] as $name) {
        $role = Role::findByName($name, 'baobab');

        expect($role->hasPermissionTo('baobab.admin.access', 'baobab'))->toBeTrue();
    }
});

it('does not grant baobab.admin.access to the visitor role', function () {
    $role = Role::findByName('visitor', 'baobab');

    expect($role->hasPermissionTo('baobab.admin.access', 'baobab'))->toBeFalse();
});

it('does not grant baobab.admin.access directly to the super-admin role, which bypasses via Gate::before', function () {
    $role = Role::findByName('super-admin', 'baobab');

    expect($role->hasPermissionTo('baobab.admin.access', 'baobab'))->toBeFalse();
});
