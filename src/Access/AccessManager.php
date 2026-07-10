<?php

declare(strict_types=1);

namespace Baobab\Access;

use Baobab\Facades\Hook;
use Baobab\Users\Models\User;
use Spatie\Permission\Contracts\Permission as PermissionContract;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

final class AccessManager
{
    public function createRole(string $name, int $level, string $guard = 'baobab'): Role
    {
        /** @var Role $role */
        $role = Role::create(['name' => $name, 'guard_name' => $guard, 'level' => $level]);

        Hook::action('baobab.access.role.created', $role);

        return $role;
    }

    /** @param array<string, mixed> $data */
    public function updateRole(Role $role, array $data): Role
    {
        $role->update($data);
        $role->refresh();

        Hook::action('baobab.access.role.updated', $role);

        return $role;
    }

    public function deleteRole(Role $role): void
    {
        $role->delete();

        Hook::action('baobab.access.role.deleted', $role);
    }

    public function grantPermission(User|Role $to, string $permission, string $guard = 'baobab'): void
    {
        $perm = Permission::findOrCreate($permission, $guard);
        $to->givePermissionTo($perm);

        Hook::action('baobab.access.granted', $to, $permission);
    }

    public function revokePermission(User|Role $from, string $permission, string $guard = 'baobab'): void
    {
        $perm = Permission::findByName($permission, $guard);
        $from->revokePermissionTo($perm);

        Hook::action('baobab.access.revoked', $from, $permission);
    }

    public function findOrCreatePermission(string $name, string $guard = 'baobab'): PermissionContract
    {
        return Permission::findOrCreate($name, $guard);
    }
}
