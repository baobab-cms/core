<?php

declare(strict_types=1);

namespace Baobab\Forms\Exceptions;

use RuntimeException;

final class DuplicateFormSlugException extends RuntimeException
{
    public static function forSlug(string $slug): self
    {
        return new self("Un formulaire utilise déjà le slug « {$slug} ».");
    }
}
