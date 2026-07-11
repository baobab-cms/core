<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * super-admin bypasses baobab.admin.access via Gate::before (spec 05 §4.1) ;
     * visitor est un rôle front sans accès admin (spec 05 §4.2). Seuls les
     * quatre rôles d'agence l'obtiennent par défaut.
     *
     * @var list<string>
     */
    private const ROLES = [
        'admin',
        'editor',
        'moderator',
        'author',
    ];

    public function up(): void
    {
        $permission = Permission::findOrCreate('baobab.admin.access', 'baobab');

        Role::whereIn('name', self::ROLES)
            ->where('guard_name', 'baobab')
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permission));
    }

    public function down(): void
    {
        $permission = Permission::findOrCreate('baobab.admin.access', 'baobab');

        Role::whereIn('name', self::ROLES)
            ->where('guard_name', 'baobab')
            ->get()
            ->each(fn (Role $role) => $role->revokePermissionTo($permission));
    }
};
