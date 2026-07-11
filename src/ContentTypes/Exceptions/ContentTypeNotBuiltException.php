<?php

declare(strict_types=1);

namespace Baobab\ContentTypes\Exceptions;

use RuntimeException;

final class ContentTypeNotBuiltException extends RuntimeException
{
    public static function forKey(string $key): self
    {
        return new self(
            "Le Content Type « {$key} » n'a pas encore été généré (module_id absent) — rien à faire évoluer."
        );
    }
}
