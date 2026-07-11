<?php

declare(strict_types=1);

namespace Baobab\ContentTypes\Exceptions;

use RuntimeException;

final class UnsafeTypeChangeException extends RuntimeException
{
    public static function forChange(string $key, string $fromType, string $toType): self
    {
        return new self(
            "Changement de type non sûr pour le champ « {$key} » : {$fromType} → {$toType}. ".
            'Cette conversion ne fait pas partie de la liste blanche des changements sûrs — migrez les données à la main.'
        );
    }
}
