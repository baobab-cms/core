<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * `baobab.audit.export` (spec 12 §8.3, §10 — M9 chantier 0.a Pass C).
 * `baobab.audit.*` reste hors `baobab.system.*` (spec §10) — patron exact
 * `baobab.system.maintenance.toggle`, migration dédiée plutôt qu'ajoutée à
 * l'ancien bootstrap `seed_core_permissions.php` qui a seedé
 * `baobab.audit.view` sans jamais l'assigner à un rôle (convention pré-M8).
 */
return new class extends Migration
{
    private const PERMISSION = 'baobab.audit.export';

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
