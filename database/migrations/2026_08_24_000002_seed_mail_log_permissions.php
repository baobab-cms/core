<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * `baobab.system.mail.log_view` et `baobab.system.mail.resend` (spec 13 §4.2,
 * §5 — M8 point 7, Pass B1). Admin+ uniquement, patron exact
 * `baobab.system.mail.templates`.
 *
 * Les deux sont semées ensemble parce qu'elles arrivent ensemble : le renvoi
 * n'a de sens que depuis le journal qu'il prolonge. Elles restent **deux**
 * permissions et non une, la spec distinguant explicitement consulter de
 * réémettre — lire un journal d'envois et déclencher un envoi ne sont pas le
 * même pouvoir.
 *
 * Semées avec leur consommateur, jamais en avance : la voisine
 * `baobab.system.mail.configure` est orpheline depuis juillet 2026 faute
 * d'écran (suivi n° 187), et c'est la leçon retenue en Pass A2.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const PERMISSIONS = [
        'baobab.system.mail.log_view',
        'baobab.system.mail.resend',
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
