<?php

declare(strict_types=1);

namespace Baobab\Studio\Exceptions;

use RuntimeException;

final class UnknownStudioRelationTargetException extends RuntimeException
{
    public static function forTarget(string $target): self
    {
        return new self(
            "Cible de relation inconnue : « {$target} » n'est ni une entité sœur du blueprint, ni un Content Type déjà construit, ni un modèle Core relationnable."
        );
    }
}
