<?php

declare(strict_types=1);

namespace Baobab\Access\Facades;

use Baobab\Access\AccessManager;
use Baobab\Users\Models\User;
use Illuminate\Support\Facades\Facade;
use Spatie\Permission\Contracts\Permission as PermissionContract;
use Spatie\Permission\Contracts\Role;

/**
 * @method static Role createRole(string $name, int $level, string $guard = 'baobab')
 * @method static Role updateRole(Role $role, array<string, mixed> $data)
 * @method static void deleteRole(Role $role)
 * @method static void grantPermission(User|Role $to, string $permission, string $guard = 'baobab')
 * @method static void revokePermission(User|Role $from, string $permission, string $guard = 'baobab')
 * @method static PermissionContract findOrCreatePermission(string $name, string $guard = 'baobab')
 *
 * @see AccessManager
 */
class Access extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return AccessManager::class;
    }
}
