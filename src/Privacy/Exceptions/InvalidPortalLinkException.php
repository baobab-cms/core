<?php

declare(strict_types=1);

namespace Baobab\Privacy\Exceptions;

use RuntimeException;

/**
 * Lien du portail inutilisable (spec 16 §4) : illisible/falsifié (`invalid`)
 * ou déjà consommé (`used`). Le portail affiche un état distinct pour chacun,
 * sans jamais dire pourquoi un lien illisible l'est.
 */
final class InvalidPortalLinkException extends RuntimeException
{
    public const INVALID = 'invalid';

    public const USED = 'used';

    private function __construct(public readonly string $reason)
    {
        parent::__construct(__('baobab::privacy.portal.state_'.$reason));
    }

    public static function invalid(): self
    {
        return new self(self::INVALID);
    }

    public static function used(): self
    {
        return new self(self::USED);
    }
}
