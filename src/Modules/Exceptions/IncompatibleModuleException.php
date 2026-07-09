<?php

declare(strict_types=1);

namespace Baobab\Modules\Exceptions;

use RuntimeException;

final class IncompatibleModuleException extends RuntimeException
{
    public static function coreVersion(string $moduleName, string $constraint): self
    {
        return new self("Le module {$moduleName} requiert le cœur Baobab {$constraint}, incompatible avec la version installée.");
    }

    public static function phpVersion(string $moduleName, string $constraint): self
    {
        return new self("Le module {$moduleName} requiert PHP {$constraint}, incompatible avec la version installée.");
    }

    public static function dependencyNotActive(string $moduleName, string $dependency): self
    {
        return new self("Le module {$moduleName} requiert {$dependency}, qui n'est pas actif.");
    }

    public static function dependencyVersionMismatch(string $moduleName, string $dependency, string $constraint, string $actualVersion): self
    {
        return new self("Le module {$moduleName} requiert {$dependency} {$constraint}, version active incompatible : {$actualVersion}.");
    }
}
