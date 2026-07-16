<?php

declare(strict_types=1);

namespace Baobab\Notify\Exceptions;

use RuntimeException;

final class InvalidNotificationException extends RuntimeException
{
    public static function unknownMailTemplate(string $key, string $mailTemplate): self
    {
        return new self("Notification « {$key} » invalide — le template d'e-mail « {$mailTemplate} » n'est déclaré ni par ce module, ni par le Core.");
    }
}
