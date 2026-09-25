<?php

declare(strict_types=1);

namespace Baobab\Users\Exceptions;

use RuntimeException;

/**
 * Le lien de confirmation d'un changement de profil est inconnu, expiré,
 * déjà utilisé ou remplacé par une demande plus récente.
 */
final class ProfileChangeLinkInvalidException extends RuntimeException {}
