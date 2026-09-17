<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * `baobab.system.backups.{view,create,download}` (spec 12 §4.2, §4.3, §10
 * — M9 chantier 0.a Pass D). Admin+ uniquement, patron exact
 * `baobab.system.queues.{view,manage}`. `.create` gate aussi la
 * suppression et l'édition des réglages de rétention — pas de permission
 * `.delete`/`.manage` dédiée, décision de séance du 17 septembre 2026
 * (spec 12 §12 décision 6).
 */
return new class extends Migration
{
    /** @var list<string> */
    private const PERMISSIONS = [
        'baobab.system.backups.view',
        'baobab.system.backups.create',
        'baobab.system.backups.download',
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
