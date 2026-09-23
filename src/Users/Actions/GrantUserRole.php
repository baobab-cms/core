<?php

declare(strict_types=1);

namespace Baobab\Users\Actions;

use Baobab\Access\AccessManager;
use Baobab\Access\Actions\AssignRole;
use Baobab\Access\Exceptions\HierarchyViolationException;
use Baobab\Users\Models\User;
use Spatie\Permission\Contracts\Role;

/**
 * Ajoute un rôle à un utilisateur depuis sa fiche ou l'API (spec 05 §5,
 * décision 5). Vérifie ce que `AssignRole` ne vérifie pas — la hiérarchie
 * (spec 05 §4.1) : jamais ses propres rôles, jamais un utilisateur ni un rôle
 * de niveau supérieur ou égal — puis lui délègue (hook, audit).
 */
final class GrantUserRole
{
    public function __construct(
        private readonly AccessManager $access,
        private readonly AssignRole $assignRole,
    ) {}

    /**
     * @throws HierarchyViolationException
     */
    public function __invoke(User $actor, User $user, Role $role): void
    {
        if ($actor->is($user)) {
            throw new HierarchyViolationException('Cannot change your own roles.');
        }

        $this->access->assertOutranks($actor, $user->level());
        $this->access->assertOutranks($actor, (int) $role->getAttribute('level'));

        if ($user->hasRole($role)) {
            return;
        }

        ($this->assignRole)($user, $role);
    }
}
