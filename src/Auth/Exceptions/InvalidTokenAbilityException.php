<?php

declare(strict_types=1);

namespace Baobab\Auth\Exceptions;

use RuntimeException;

/**
 * Un token ne peut porter que des permissions que son créateur possède
 * (spec 08 §4.1) — levée par `Baobab\Auth\Actions\CreateApiToken` quand
 * l'ability demandée n'est pas parmi les permissions actuelles de l'acteur.
 */
final class InvalidTokenAbilityException extends RuntimeException
{
    public static function forAbility(string $ability): self
    {
        return new self(
            "Vous ne pouvez pas créer un jeton avec l'ability « {$ability} » : vous ne possédez pas cette permission."
        );
    }
}
