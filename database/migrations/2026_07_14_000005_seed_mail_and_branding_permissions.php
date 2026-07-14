<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * `baobab.system.mail.configure` (spec 13 §5 — corrigée en `baobab.system.mail.*`,
 * suivi n° 33, pour rester cohérente avec le préfixe réservé `baobab.*`) et
 * `baobab.system.branding.manage` (spec-admin §11.1). Aucun écran ne consomme
 * encore `mail.configure` (transport = M8) ; elle est posée pour que
 * `SendTestMail`/le CLI puissent s'y raccrocher sans re-décision.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const PERMISSIONS = [
        'baobab.system.mail.configure',
        'baobab.system.branding.manage',
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
        Permission::whereIn('name', self::PERMISSIONS)
            ->where('guard_name', 'baobab')
            ->delete();
    }
};
