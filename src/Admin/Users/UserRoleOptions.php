<?php

declare(strict_types=1);

namespace Baobab\Admin\Users;

use Baobab\Users\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Spatie\Permission\Models\Role;

/**
 * Les rôles qu'un acteur peut attribuer (spec 05 §4.1) : de niveau
 * strictement inférieur au sien. Sert le formulaire d'invitation et la carte
 * Rôles de la fiche — la vue ne calcule rien.
 */
final class UserRoleOptions
{
    /**
     * @return Collection<int, Role>
     */
    public function assignableBy(User $actor): Collection
    {
        return Role::query()
            ->where('guard_name', 'baobab')
            ->where('level', '<', $actor->level())
            ->orderByDesc('level')
            ->orderBy('name')
            ->get();
    }
}
