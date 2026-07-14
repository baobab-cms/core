<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * `baobab.trash.purge` (spec 09 §8) : permission globale unique couvrant la
 * purge définitive de « tout modèle avec soft deletes » — contrairement aux
 * permissions `content.{type}.delete_any`/`baobab.media.delete_any` qui
 * gouvernent la mise à la corbeille par domaine, la purge (irréversible) est
 * volontairement transverse. Accordée à Éditeur+ par défaut (spec 09 §8).
 * Note : la purge média (M4) utilise encore `baobab.media.delete_any`, pas
 * cette permission — incohérence documentée dans le suivi, non résolue ici.
 */
return new class extends Migration
{
    private const PERMISSION = 'baobab.trash.purge';

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
