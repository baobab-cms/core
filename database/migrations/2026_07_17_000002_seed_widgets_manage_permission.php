<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * `baobab.widgets.manage` (spec 10 §3.3) — écran `admin/widgets` :
 * créer/éditer des instances de widgets, les assigner aux zones du thème
 * actif. Éditeur+ par défaut, aligné sur `baobab.menus.manage` (même écran
 * « Apparence », la spec ne précise pas de niveau explicite pour ce point).
 */
return new class extends Migration
{
    private const PERMISSION = 'baobab.widgets.manage';

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
