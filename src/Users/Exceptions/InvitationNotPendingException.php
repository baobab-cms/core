<?php

declare(strict_types=1);

namespace Baobab\Users\Exceptions;

use RuntimeException;

/**
 * Renvoi d'une invitation déjà acceptée (spec 05 §5, décision 5) : le compte
 * a son mot de passe, un nouveau lien n'aurait plus d'objet.
 */
final class InvitationNotPendingException extends RuntimeException {}
