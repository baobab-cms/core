<?php

declare(strict_types=1);

namespace Baobab\ContentTypes\Exceptions;

use RuntimeException;

final class DestructiveChangeNotConfirmedException extends RuntimeException
{
    /**
     * @param  list<string>  $removedKeys
     */
    public static function forFields(array $removedKeys): self
    {
        $keys = implode(', ', $removedKeys);

        return new self(
            "Cette évolution supprime le(s) champ(s) « {$keys} » et leurs données — ".
            'confirmez explicitement (confirmDestructive) pour continuer.'
        );
    }
}
