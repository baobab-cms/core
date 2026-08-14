<?php

declare(strict_types=1);

namespace Baobab\Studio\Exceptions;

use RuntimeException;

/**
 * Rendre obligatoire un champ qui ne l'était pas resserre sa colonne en NOT
 * NULL, ce que la base refuse tant qu'une ligne y porte NULL.
 *
 * Sans cette garde, l'échec arriverait sous la forme d'une `QueryException` du
 * driver au milieu d'une migration — le même symptôme illisible que le n° 137
 * produisait à l'enregistrement d'une entrée. Le nom de la colonne et le nombre
 * de lignes fautives sont ce dont l'utilisateur a besoin pour décider : remplir
 * les lignes, ou laisser le champ facultatif.
 */
final class ColumnHasNullsException extends RuntimeException
{
    public static function forColumn(string $table, string $column, int $rows): self
    {
        return new self(
            "Le champ « {$column} » ne peut pas devenir obligatoire : {$rows} ligne(s) de la table ".
            "« {$table} » n'ont pas de valeur. Renseignez-les, ou laissez le champ facultatif."
        );
    }
}
