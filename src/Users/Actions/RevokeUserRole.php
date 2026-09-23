<?php

declare(strict_types=1);

namespace Baobab\Users\Actions;

use Baobab\Access\AccessManager;
use Baobab\Access\Actions\RemoveRole;
use Baobab\Access\Exceptions\AdminLockoutException;
use Baobab\Access\Exceptions\HierarchyViolationException;
use Baobab\Users\Models\User;
use Spatie\Permission\Contracts\Role;

/**
 * Retire un rôle à un utilisateur depuis sa fiche ou l'API (spec 05 §5,
 * décision 5). Même contrôle hiérarchique que `GrantUserRole`, puis
 * délégation à `RemoveRole`, qui garde l'anti-lockout (spec 05 §6.1).
 */
final class RevokeUserRole
{
    public function __construct(
        private readonly AccessManager $access,
        private readonly RemoveRole $removeRole,
    ) {}

    /**
     * @throws HierarchyViolationException
     * @throws AdminLockoutException
     */
    public function __invoke(User $actor, User $user, Role $role): void
    {
        if ($actor->is($user)) {
            throw new HierarchyViolationException('Cannot change your own roles.');
        }

        $this->access->assertOutranks($actor, $user->level());
        $this->access->assertOutranks($actor, (int) $role->getAttribute('level'));

        if (! $user->hasRole($role)) {
            return;
        }

        ($this->removeRole)($user, $role);
    }
}
