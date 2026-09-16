<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * `baobab.system.scheduler.view` et `baobab.system.scheduler.run` (spec 12
 * §2.3, §10 — M9 chantier 0.a Pass B). Admin+ uniquement, patron exact
 * `baobab.system.maintenance.toggle`.
 *
 * Les deux sont semées ensemble, patron `seed_mail_log_permissions.php` :
 * `.view` consulte l'écran, `.run` couvre les actions ponctuelles
 * (« Exécuter maintenant », suspendre/réactiver une tâche de module) — la
 * spec ne nomme pas de permission dédiée à la suspension, qui rejoint donc
 * `.run` plutôt qu'une troisième permission inventée.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const PERMISSIONS = [
        'baobab.system.scheduler.view',
        'baobab.system.scheduler.run',
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
