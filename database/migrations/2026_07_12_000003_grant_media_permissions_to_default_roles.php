<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Admin/Éditeur : accès complet, y compris `_any` et `upload_svg` (spec
     * 05 §4 : l'Éditeur a « médias » sans restriction). Auteur/Modérateur :
     * uniquement leurs propres médias, pas de SVG (spec 06 §3.2 : désactivé
     * par défaut sous Éditeur).
     *
     * @var array<string, list<string>>
     */
    private const GRANTS = [
        'admin' => [
            'baobab.media.view', 'baobab.media.upload', 'baobab.media.update',
            'baobab.media.update_any', 'baobab.media.delete', 'baobab.media.delete_any',
            'baobab.media.upload_svg',
        ],
        'editor' => [
            'baobab.media.view', 'baobab.media.upload', 'baobab.media.update',
            'baobab.media.update_any', 'baobab.media.delete', 'baobab.media.delete_any',
            'baobab.media.upload_svg',
        ],
        'author' => [
            'baobab.media.view', 'baobab.media.upload', 'baobab.media.update', 'baobab.media.delete',
        ],
        'moderator' => [
            'baobab.media.view', 'baobab.media.upload', 'baobab.media.update', 'baobab.media.delete',
        ],
    ];

    public function up(): void
    {
        foreach (self::GRANTS as $roleName => $permissions) {
            $role = Role::where('name', $roleName)->where('guard_name', 'baobab')->first();

            if ($role === null) {
                continue;
            }

            foreach ($permissions as $permission) {
                $role->givePermissionTo(Permission::findOrCreate($permission, 'baobab'));
            }
        }
    }

    public function down(): void
    {
        foreach (self::GRANTS as $roleName => $permissions) {
            $role = Role::where('name', $roleName)->where('guard_name', 'baobab')->first();

            if ($role === null) {
                continue;
            }

            foreach ($permissions as $permission) {
                $role->revokePermissionTo(Permission::findOrCreate($permission, 'baobab'));
            }
        }
    }
};
