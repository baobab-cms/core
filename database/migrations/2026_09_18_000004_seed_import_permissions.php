<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * `baobab.system.import.{view,run}` (spec 12 §5.2, §5.3, §10 — M9 chantier
 * 0.a Pass F2). Admin+ uniquement, patron exact `baobab.system.export.{view,create}` —
 * `.run` est le nommage exact de la spec §5.2 pour l'exécution (dry-run
 * compris, `.view` couvre l'écran et l'historique).
 */
return new class extends Migration
{
    /** @var list<string> */
    private const PERMISSIONS = [
        'baobab.system.import.view',
        'baobab.system.import.run',
    ];

    /** @var list<string> */
    private const DEFAULT_ROLES = ['admin'];

    public function up(): void
    {
        foreach (self::PERMISSIONS as $name) {
            $permission = Permission::findOrCreate($name, 'baobab');

            foreach (self::DEFAULT_ROLES as $roleName) {
                $role = Role::where('name', $roleName)->where('guard_name', 'baobab')->first();

                $role?->givePermissionTo($permission);
            }
        }
    }

    public function down(): void
    {
        Permission::whereIn('name', self::PERMISSIONS)->where('guard_name', 'baobab')->delete();
    }
};
