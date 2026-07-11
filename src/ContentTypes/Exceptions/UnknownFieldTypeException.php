<?php

declare(strict_types=1);

namespace Baobab\ContentTypes\Exceptions;

use RuntimeException;

final class UnknownFieldTypeException extends RuntimeException
{
    public static function forKey(string $key): self
    {
        return new self("Aucun type de champ enregistré pour la clé « {$key} ».");
    }
}
