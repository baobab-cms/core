<?php

declare(strict_types=1);

namespace Baobab\ContentTypes\Exceptions;

use RuntimeException;

final class UnknownRelationTargetException extends RuntimeException
{
    public static function forTarget(string $target): self
    {
        return new self(
            "Cible de relation inconnue : « {$target} » n'est ni un Content Type déjà construit, ni un modèle Core relationnable."
        );
    }
}
