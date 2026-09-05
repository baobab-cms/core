<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * `baobab.system.demo_content.manage` (spec 03 §7, M8 point 3, Pass D3) —
 * écran dédié « Contenu de démonstration », qui ne fait que déclencher
 * `RemoveDemoContent`. Admin+ uniquement, patron exact
 * `baobab.system.modules.manage`.
 */
return new class extends Migration
{
    private const PERMISSION = 'baobab.system.demo_content.manage';

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
