<?php

declare(strict_types=1);

namespace Baobab\Admin\Access;

use Baobab\Modules\Models\ModulePermission;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Service de lecture pure (même précédent que SidebarBuilder) : construit la
 * grille rôles × permissions consommée par l'écran de matrice (spec 05 §5).
 */
final class PermissionMatrixBuilder
{
    /**
     * @return array{
     *     roles: EloquentCollection<int, Role>,
     *     groups: Collection<int, array{label: string, active: bool, permissions: Collection<int, array{name: string, label: string}>}>,
     *     grants: array<int, array<string, bool>>,
     * }
     */
    public function build(): array
    {
        $roles = Role::query()
            ->where('guard_name', 'baobab')
            ->orderByDesc('level')
            ->with('permissions')
            ->get();

        $permissions = Permission::query()
            ->where('guard_name', 'baobab')
            ->orderBy('name')
            ->get();

        /** @var Collection<string, ModulePermission> $modulePermissions */
        $modulePermissions = ModulePermission::query()->with('module')->get()->keyBy('key');

        $groups = $permissions
            ->groupBy(function (Permission $permission) use ($modulePermissions): string {
                $module = $modulePermissions->get($permission->name)?->module;

                return $module !== null ? $module->name : 'core';
            })
            ->map(function (Collection $groupPermissions) use ($modulePermissions): array {
                /** @var Permission $first */
                $first = $groupPermissions->first();
                $module = $modulePermissions->get($first->name)?->module;

                return [
                    'label' => $module !== null ? $module->title : (string) __('baobab::admin.access.core_group'),
                    'active' => $module === null || $module->status === 'active',
                    'permissions' => $groupPermissions
                        ->map(function (Permission $permission) use ($modulePermissions): array {
                            $modulePermission = $modulePermissions->get($permission->name);

                            return [
                                'name' => $permission->name,
                                'label' => $modulePermission !== null ? $modulePermission->label : $permission->name,
                            ];
                        })
                        ->values(),
                ];
            })
            ->values();

        $grants = [];

        foreach ($roles as $role) {
            /** @var array<string, bool> $rolePermissions */
            $rolePermissions = [];

            foreach ($role->permissions as $rolePermission) {
                /** @var Permission $rolePermission */
                $rolePermissions[$rolePermission->name] = true;
            }

            $grants[(int) $role->id] = $rolePermissions;
        }

        return ['roles' => $roles, 'groups' => $groups, 'grants' => $grants];
    }
}
