<?php

declare(strict_types=1);

namespace Baobab\Themes\Exceptions;

use RuntimeException;

final class ThemeBlueprintAlreadyExistsException extends RuntimeException
{
    public static function forSlug(string $slug): self
    {
        return new self("Un theme.json existe déjà pour « {$slug} » — édite-le plutôt que d'en créer un nouveau.");
    }
}
