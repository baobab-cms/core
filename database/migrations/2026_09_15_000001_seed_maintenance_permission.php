<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * `baobab.system.maintenance.toggle` (spec 12 §6.1, §10) — bascule le mode
 * maintenance, et conditionne l'accès à l'admin pendant qu'il est actif.
 * Admin+ uniquement, patron exact `baobab.system.seo.manage`.
 */
return new class extends Migration
{
    private const PERMISSION = 'baobab.system.maintenance.toggle';

    /** @var list<string> */
    private const DEFAULT_ROLES = ['admin'];

    public function up(): void
    {
        $permission = Permission::findOrCreate(self::PERMISSION, 'baobab');

        foreach (self::DEFAULT_ROLES as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'baobab')->first();

            $role?->givePermissionTo($permission);
        }
    }

    public function down(): void
    {
        Permission::where('name', self::PERMISSION)->where('guard_name', 'baobab')->delete();
    }
};
