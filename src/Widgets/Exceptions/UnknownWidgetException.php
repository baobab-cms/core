<?php

declare(strict_types=1);

namespace Baobab\Widgets\Exceptions;

use RuntimeException;

final class UnknownWidgetException extends RuntimeException
{
    public static function forKey(string $key): self
    {
        return new self("Aucun widget enregistré pour la clé « {$key} ».");
    }
}
