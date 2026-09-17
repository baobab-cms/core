<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * `baobab.system.health.view` (spec 12 §7.1, §10 — M9 chantier 0.a Pass
 * E). Une seule permission : le tableau de bord santé est en lecture
 * seule, le déclenchement manuel (« Actualiser ») n'a pas de permission
 * dédiée dans la spec — gaté par la même, patron `baobab.system.
 * maintenance.toggle` (permission unique couvrant vue et action).
 */
return new class extends Migration
{
    private const PERMISSION = 'baobab.system.health.view';

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
