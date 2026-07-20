<?php

declare(strict_types=1);

namespace Baobab\Search\Exceptions;

use RuntimeException;

final class UnknownSearchSourceException extends RuntimeException
{
    public static function forKey(string $key): self
    {
        return new self("Aucune source de recherche enregistrée pour la clé « {$key} ».");
    }
}
