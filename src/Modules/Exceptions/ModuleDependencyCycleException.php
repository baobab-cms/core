<?php

declare(strict_types=1);

namespace Baobab\Modules\Exceptions;

use RuntimeException;

final class ModuleDependencyCycleException extends RuntimeException
{
    /**
     * @param  list<string>  $cyclePath
     */
    public static function detected(array $cyclePath): self
    {
        return new self('Cycle de dépendances détecté : '.implode(' → ', $cyclePath));
    }
}
