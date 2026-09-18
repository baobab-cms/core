<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * `baobab.system.export.{view,create}` (spec 12 §5.2, §10 — M9 chantier 0.a
 * Pass F1). Admin+ uniquement, patron exact `baobab.system.backups.{view,create}` —
 * `.view` couvre l'écran et le téléchargement, `.create` déclenche l'export
 * (CLI et admin partagent la même permission, aucune permission `.download`
 * dédiée : le périmètre est plus restreint qu'une sauvegarde, pas de contenu
 * sensible propre à protéger au-delà de la vue d'ensemble).
 */
return new class extends Migration
{
    /** @var list<string> */
    private const PERMISSIONS = [
        'baobab.system.export.view',
        'baobab.system.export.create',
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
