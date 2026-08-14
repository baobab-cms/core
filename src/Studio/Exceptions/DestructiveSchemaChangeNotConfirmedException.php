<?php

declare(strict_types=1);

namespace Baobab\Studio\Exceptions;

use RuntimeException;

/**
 * Pendant de `DestructiveChangeNotConfirmedException` (chemin Content Type) pour
 * le schéma d'un module : supprimer une colonne supprime ses données, et cela ne
 * se décide pas en régénérant.
 */
final class DestructiveSchemaChangeNotConfirmedException extends RuntimeException
{
    /**
     * @param  list<string>  $columns  Colonnes qualifiées `table.colonne`.
     */
    public static function forColumns(array $columns): self
    {
        $list = implode(', ', $columns);

        return new self(
            "Cette évolution supprime la ou les colonnes « {$list} » et leurs données — ".
            'confirmez explicitement (confirmDestructive) pour continuer.'
        );
    }
}
