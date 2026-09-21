<?php

declare(strict_types=1);

namespace Baobab\Privacy\Support;

use Baobab\Access\Exceptions\AdminLockoutException;
use Baobab\Privacy\Subject;
use Baobab\Users\Models\User;

/**
 * Garde-fou commun de l'effacement (spec 16 §4.3) : on n'efface jamais le
 * dernier super-administrateur, sans quoi le site n'aurait plus aucun accès
 * d'administration. Vérifié à la création d'une demande (refus immédiat) et
 * de nouveau à l'exécution — le contexte peut avoir changé pendant le délai.
 */
final class ErasureGuard
{
    /**
     * @throws AdminLockoutException
     */
    public function assertErasable(Subject $subject): void
    {
        $user = $subject->userId === null
            ? ($subject->email === null ? null : User::query()->whereRaw('LOWER(email) = ?', [$subject->email])->first())
            : User::query()->find($subject->userId);

        if ($user !== null && $this->isLastSuperAdmin($user)) {
            throw new AdminLockoutException(__('baobab::privacy.erasure.last_super_admin'));
        }
    }

    private function isLastSuperAdmin(User $user): bool
    {
        return $user->hasRole('super-admin', 'baobab')
            && User::role('super-admin', 'baobab')->count() <= 1;
    }
}
