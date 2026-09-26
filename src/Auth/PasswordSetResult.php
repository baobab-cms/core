<?php

declare(strict_types=1);

namespace Baobab\Auth;

use Baobab\Users\Models\User;

/**
 * Résultat de `SetUserPassword` (spec 05 §6.1).
 *
 * `generatedPassword` n'est renseigné que lorsque l'Action a dû en forger un —
 * il n'est **jamais** relisible ensuite, et c'est le seul moment où
 * l'afficher a un sens.
 */
final readonly class PasswordSetResult
{
    public function __construct(
        public User $user,
        public ?string $generatedPassword = null,
    ) {}
}
