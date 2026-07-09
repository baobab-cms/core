<?php

declare(strict_types=1);

namespace Baobab\Modules\Exceptions;

use RuntimeException;

final class ModuleHasActiveDependentsException extends RuntimeException
{
    /**
     * @param  list<string>  $dependents
     */
    public static function dependents(string $moduleName, array $dependents): self
    {
        return new self(sprintf(
            'Impossible de désactiver %s : modules actifs qui en dépendent : %s.',
            $moduleName,
            implode(', ', $dependents),
        ));
    }
}
