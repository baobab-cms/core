<?php

declare(strict_types=1);

namespace Baobab\Modules\Exceptions;

use RuntimeException;

final class ModuleNotFoundException extends RuntimeException
{
    public static function named(string $name): self
    {
        return new self("Module introuvable : {$name}.");
    }
}
