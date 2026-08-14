<?php

declare(strict_types=1);

namespace Baobab\Branding\Exceptions;

use RuntimeException;

/**
 * Un groupe/clé hors du vocabulaire de `DesignTokenSchema` (spec 18 §2.2).
 *
 * Typée plutôt que fourre-tout, pour que l'appelant puisse la rattraper et la
 * traduire — patron acté en Pass A du point 9 (suivi n° 113) : les exceptions
 * rattrapées sont énumérées, jamais un `RuntimeException` générique.
 */
final class UnknownDesignTokenException extends RuntimeException
{
    public static function named(string $group, string $key): self
    {
        return new self("Token de marque inconnu : « {$group}.{$key} ».");
    }
}
