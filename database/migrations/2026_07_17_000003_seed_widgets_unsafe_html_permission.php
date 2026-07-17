<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * `baobab.widgets.unsafe_html` (spec 10 §4 décision 3) — autorise la
 * création/modification d'une instance du widget « HTML personnalisé »
 * (sortie non nettoyée, risque nommé explicitement par la spec). Admin+
 * uniquement par défaut, patron `baobab.system.themes.manage`.
 */
return new class extends Migration
{
    private const PERMISSION = 'baobab.widgets.unsafe_html';

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
