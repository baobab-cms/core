<?php

declare(strict_types=1);

namespace Baobab\Access;

use Baobab\Access\Exceptions\HierarchyViolationException;
use Baobab\Facades\Hook;
use Baobab\Users\Models\User;
use Spatie\Permission\Contracts\Permission as PermissionContract;
use Spatie\Permission\Contracts\Role as RoleContract;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

final class AccessManager
{
    /**
     * Spec 05 §4.1 : un acteur ne peut créer/modifier/supprimer (ou usurper)
     * que des rôles/utilisateurs de niveau strictement inférieur au sien.
     * No-op sans acteur (contexte système/CLI/tests) — même tolérance que
     * les autres garde-fous (RemoveRole::isActingOnSelf()).
     */
    public function assertOutranks(?User $actor, int $targetLevel): void
    {
        if ($actor === null) {
            return;
        }

        if ($targetLevel >= $actor->level()) {
            throw new HierarchyViolationException(
                "Cannot act on a role or user of level {$targetLevel}: actor's level ({$actor->level()}) is not strictly higher.",
            );
        }
    }

    /**
     * Vrai quand `$user` est le seul Super Admin **actif** : le retirer (rôle
     * ôté, compte désactivé ou effacé) laisserait le site sans aucun accès
     * complet. Un Super Admin désactivé ne compte pas (spec 05 §5, décision
     * 5 k) — sans quoi il resterait un « dernier » fantôme —, et n'est lui-même
     * jamais « le dernier » : le retirer ne coûte aucun accès.
     */
    public function isLastActiveSuperAdmin(User $user): bool
    {
        if ($user->isDeactivated() || ! $user->hasRole('super-admin', 'baobab')) {
            return false;
        }

        return User::role('super-admin', 'baobab')->notDeactivated()->count() <= 1;
    }

    public function createRole(string $name, int $level, string $guard = 'baobab'): RoleContract
    {
        /** @var RoleContract $role */
        $role = Role::create(['name' => $name, 'guard_name' => $guard, 'level' => $level]);

        Hook::action('baobab.access.role.created', $role);

        return $role;
    }

    /**
     * `forceFill()` plutôt que `update()` : $data vient toujours d'une Action
     * qui a déjà validé/restreint ses clés (name, level, requires_two_factor…),
     * jamais d'une requête brute — le garde-fou de mass-assignment de Laravel
     * n'a rien à protéger ici. Nécessaire en pratique : ce garde-fou met en
     * cache, par classe et pour la durée du process, la liste des colonnes de
     * la table au premier appel — en test, la migration de seed des rôles
     * s'exécute avant toute migration ajoutant une colonne plus récente
     * (`baobab_roles.requires_two_factor`), ce qui fige un cache obsolète et
     * ferait ignorer silencieusement cette colonne sur chaque mise à jour.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateRole(RoleContract $role, array $data): RoleContract
    {
        $before = $role->getOriginal();

        $role->forceFill($data)->save();
        $role->refresh();

        Hook::action('baobab.access.role.updated', $role, $before);

        return $role;
    }

    public function deleteRole(RoleContract $role): void
    {
        $role->delete();

        Hook::action('baobab.access.role.deleted', $role);
    }

    public function grantPermission(User|RoleContract $to, string $permission, string $guard = 'baobab'): void
    {
        $perm = Permission::findOrCreate($permission, $guard);
        $to->givePermissionTo($perm);

        Hook::action('baobab.access.granted', $to, $permission);
    }

    public function revokePermission(User|RoleContract $from, string $permission, string $guard = 'baobab'): void
    {
        $perm = Permission::findByName($permission, $guard);
        $from->revokePermissionTo($perm);

        Hook::action('baobab.access.revoked', $from, $permission);
    }

    public function findOrCreatePermission(string $name, string $guard = 'baobab'): PermissionContract
    {
        return Permission::findOrCreate($name, $guard);
    }

    public function assignRole(User $user, RoleContract $role): void
    {
        $user->assignRole($role);

        Hook::action('baobab.access.role.assigned', $user, $role);
    }

    public function removeRole(User $user, RoleContract $role): void
    {
        $user->removeRole($role);

        Hook::action('baobab.access.role.removed', $user, $role);
    }
}
