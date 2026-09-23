<?php

declare(strict_types=1);

namespace Baobab\Users;

use Baobab\Users\Models\User;

/**
 * Qui peut consulter la liste et les fiches des utilisateurs (spec 05 §5) :
 * qui gère les comptes (`baobab.users.manage`, décision 5) comme qui peut les
 * usurper (`baobab.users.impersonate`). Un seul endroit décide, pour l'admin,
 * la barre latérale et l'API.
 */
final class UserDirectory
{
    public const ABILITY = 'browseUsers';

    public static function allows(User $user): bool
    {
        return $user->can('baobab.users.manage') || $user->can('baobab.users.impersonate');
    }
}
