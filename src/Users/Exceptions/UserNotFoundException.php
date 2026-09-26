<?php

declare(strict_types=1);

namespace Baobab\Users\Exceptions;

use RuntimeException;

/**
 * Aucun compte pour cette adresse (spec 05 §6.1, commande de secours du mot
 * de passe) : la commande ne crée jamais un compte, elle en change le mot de
 * passe.
 */
final class UserNotFoundException extends RuntimeException {}
