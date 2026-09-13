<?php

declare(strict_types=1);

namespace Baobab\Access\Actions;

use Baobab\Access\Models\DirectPermissionGrant;
use Baobab\Users\Models\User;
use Spatie\Permission\Models\Permission;

/**
 * Délègue le revoke Spatie lui-même à {@see RevokePermission} — même
 * garde-fou anti-lockout sur `baobab.admin.access` — et retire l'exception
 * du suivi ({@see DirectPermissionGrant}) une fois le revoke effectué.
 */
final class RevokeDirectPermission
{
    public function __invoke(User $user, string $permission, string $guard = 'baobab'): void
    {
        app(RevokePermission::class)($user, $permission, $guard);

        $permissionModel = Permission::findByName($permission, $guard);

        DirectPermissionGrant::query()
            ->where('user_id', $user->id)
            ->where('permission_id', $permissionModel->getKey())
            ->delete();
    }
}
