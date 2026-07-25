<?php

declare(strict_types=1);

namespace Baobab\Branding\Exceptions;

use RuntimeException;

final class UnknownBrandProfileException extends RuntimeException
{
    public static function named(string $slug): self
    {
        return new self("Profil de marque inconnu : « {$slug} ».");
    }
}
