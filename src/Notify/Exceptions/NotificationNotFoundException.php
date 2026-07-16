<?php

declare(strict_types=1);

namespace Baobab\Notify\Exceptions;

use RuntimeException;

/**
 * Spec 11 §6 : une notification applicative n'existe pas sans déclaration.
 * Levée par `NotificationRegistry` quand `Notifier::send()` est appelé avec
 * une clé qu'aucun module actif (ni le Core) n'a déclarée.
 */
final class NotificationNotFoundException extends RuntimeException
{
    public static function forKey(string $key): self
    {
        return new self("Aucune notification déclarée pour la clé « {$key} ».");
    }
}
