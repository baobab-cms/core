<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * `baobab.system.content_types.manage` (spec 02 §2.1, M8 point 2, Pass B) —
 * écran de construction des Content Types. Admin+ uniquement, patron exact
 * `baobab.system.studio.manage`.
 *
 * Distincte des permissions `content.{key}.*`, qui gouvernent les *entrées*
 * d'un type : celle-ci gouverne le type lui-même, c'est-à-dire le schéma.
 * Pouvoir publier des articles n'a jamais impliqué de pouvoir ajouter une
 * colonne à la table qui les porte.
 */
return new class extends Migration
{
    private const PERMISSION = 'baobab.system.content_types.manage';

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
