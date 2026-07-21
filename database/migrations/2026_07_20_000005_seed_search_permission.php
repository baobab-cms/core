<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * `baobab.system.search.manage` (spec 11 §9 — la spec écrit
 * `system.search.manage`, préfixe `baobab.` ajouté par la convention Core,
 * comme toutes les permissions système) — écran « Recherche ». Admin+
 * uniquement, patron exact `baobab.system.api.manage`.
 */
return new class extends Migration
{
    private const PERMISSION = 'baobab.system.search.manage';

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
