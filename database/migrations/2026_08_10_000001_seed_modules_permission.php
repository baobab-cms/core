<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * `baobab.system.modules.manage` (spec-modules §3, M8 point 9, Pass A) — écran
 * « Modules », cycle de vie générique en admin. Admin+ uniquement, patron exact
 * `baobab.system.studio.manage`.
 *
 * Nommage : la spec §3 n'énonce `baobab.modules.*` que pour l'API REST, jamais
 * pour l'écran ; on suit donc la convention des cinq écrans système existants
 * (`themes`, `studio`, `branding`, `fonts`, `search`) plutôt que d'inventer une
 * troisième forme.
 */
return new class extends Migration
{
    private const PERMISSION = 'baobab.system.modules.manage';

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
