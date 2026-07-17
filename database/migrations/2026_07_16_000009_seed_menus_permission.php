<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * `baobab.menus.manage` (spec 10 §2.3) — écran `admin/menus` : créer/éditer
 * des menus, les assigner aux emplacements du thème actif. « Éditeur+ » par
 * défaut (spec).
 */
return new class extends Migration
{
    private const PERMISSION = 'baobab.menus.manage';

    /** @var list<string> */
    private const DEFAULT_ROLES = ['admin', 'editor'];

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
