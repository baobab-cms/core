<?php

declare(strict_types=1);

namespace Baobab\Users\Exceptions;

use RuntimeException;

/**
 * Désactivation ou réactivation sans objet (spec 05 §5, décision 5 k) : on ne
 * désactive qu'un compte actif — ni déjà désactivé, ni en attente
 * d'invitation, dont l'annulation est le chemin (décision 5 i) — et on ne
 * réactive qu'un compte désactivé.
 */
final class InvalidAccountStateException extends RuntimeException {}
