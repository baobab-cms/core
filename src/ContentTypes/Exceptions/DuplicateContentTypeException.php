<?php

declare(strict_types=1);

namespace Baobab\ContentTypes\Exceptions;

use RuntimeException;

final class DuplicateContentTypeException extends RuntimeException
{
    public static function forKey(string $key): self
    {
        return new self("Un Content Type portant la clé « {$key} » existe déjà.");
    }
}
