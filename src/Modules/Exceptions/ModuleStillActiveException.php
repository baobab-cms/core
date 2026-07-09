<?php

declare(strict_types=1);

namespace Baobab\Modules\Exceptions;

use RuntimeException;

final class ModuleStillActiveException extends RuntimeException
{
    public static function named(string $name): self
    {
        return new self("Impossible de désinstaller {$name} : désactivez-le d'abord.");
    }
}
