<?php

declare(strict_types=1);

namespace Baobab\Privacy\Exceptions;

use RuntimeException;

/**
 * Une demande du portail confirmée par la personne mais refusée (spec 16 §4,
 * décision 18) : dernier super-administrateur, effacement déjà planifié. Le
 * message est le motif lisible, dit à la personne par la page et par mail —
 * elle a prouvé qu'elle possède la boîte, il n'y a plus d'oracle à protéger.
 * `reason` est la clé stable, pour l'audit.
 */
final class PortalRequestRefusedException extends RuntimeException
{
    public const LAST_SUPER_ADMIN = 'last_super_admin';

    public const ALREADY_SCHEDULED = 'already_scheduled';

    private function __construct(public readonly string $reason)
    {
        parent::__construct(__('baobab::privacy.portal.refused_'.$reason));
    }

    public static function lastSuperAdmin(): self
    {
        return new self(self::LAST_SUPER_ADMIN);
    }

    public static function alreadyScheduled(): self
    {
        return new self(self::ALREADY_SCHEDULED);
    }
}
