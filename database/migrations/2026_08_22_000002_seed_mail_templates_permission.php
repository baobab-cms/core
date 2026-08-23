<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * `baobab.system.mail.templates` (spec 13 §5, M8 point 7, Pass A2) — écran de
 * personnalisation des templates d'e-mails. Admin+ uniquement, patron exact
 * `baobab.system.content_types.manage`.
 *
 * **Semée avec son écran, et pas avant.** La permission voisine
 * `baobab.system.mail.configure` avait été posée en juillet 2026 « pour que le
 * CLI puisse s'y raccrocher sans re-décision » : elle est restée sans
 * consommateur, l'écran qui devait la servir n'appartenant à aucun point de
 * roadmap (suivi n° 187). Une permission sans son écran n'est pas une avance,
 * c'est une orpheline.
 */
return new class extends Migration
{
    private const PERMISSION = 'baobab.system.mail.templates';

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
