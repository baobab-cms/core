<?php

declare(strict_types=1);

namespace Baobab\Install;

use Baobab\Users\Models\User;

/**
 * Résultat de la création du premier compte (spec 15 §4, étape 4).
 *
 * `generatedPassword` n'est renseigné que lorsque l'Action a dû en forger un —
 * il n'est **jamais** relisible ensuite, et c'est le seul moment où
 * l'afficher a un sens.
 */
final readonly class SuperAdminResult
{
    public function __construct(
        public User $user,
        public bool $created,
        public ?string $generatedPassword = null,
    ) {}
}
