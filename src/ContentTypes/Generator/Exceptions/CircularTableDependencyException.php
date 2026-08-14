<?php

declare(strict_types=1);

namespace Baobab\ContentTypes\Generator\Exceptions;

use RuntimeException;

/**
 * Deux entités qui se référencent mutuellement ne peuvent pas être créées :
 * chacune attend l'autre.
 *
 * Levée **à la génération**, où le graphe est connu, plutôt que subie à
 * l'installation sous la forme d'un `SQLSTATE[HY000] 1824 Failed to open the
 * referenced table` — suivi n° 120. Le message nomme le cycle : c'est ce qui
 * permet de le corriger.
 */
final class CircularTableDependencyException extends RuntimeException
{
    /**
     * @param  list<string>  $cycle
     */
    public static function forCycle(array $cycle): self
    {
        return new self(sprintf(
            'Dépendance circulaire entre les tables du module : %s. '
            .'Chaque table attend la création de la suivante. Rendez l\'une des relations facultative '
            .'en la déclarant depuis une seule des deux entités.',
            implode(' → ', $cycle),
        ));
    }
}
