<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * `baobab.system.forms.manage`, `.submissions_view`, `.submissions_export`,
 * `.submissions_delete` (spec 14 §9, amendée le 5 septembre 2026 — suivi
 * n° 259 — pour le préfixe `baobab.system.*`, M8 point 6 Pass B1). Semées
 * ensemble parce qu'elles arrivent ensemble avec leur écran, jamais en
 * avance (patron `seed_mail_log_permissions.php`).
 *
 * Quatre et non une seule : gérer un formulaire (créer/modifier/supprimer/
 * exporter-importer le blueprint) et consulter/exporter/supprimer des
 * soumissions sont des pouvoirs distincts — l'un touche la définition, les
 * trois autres des données personnelles (spec 14 §6.2, §6.4).
 */
return new class extends Migration
{
    /** @var list<string> */
    private const PERMISSIONS = [
        'baobab.system.forms.manage',
        'baobab.system.forms.submissions_view',
        'baobab.system.forms.submissions_export',
        'baobab.system.forms.submissions_delete',
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
