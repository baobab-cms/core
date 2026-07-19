<?php

declare(strict_types=1);

namespace Baobab\Api\GraphQL;

/**
 * Résolveur du champ `Query.ping` (M7 point 3, `ressources/graphql/schema-core.graphql`)
 * — un champ racine sans directive de résolveur explicite lève une erreur à
 * la construction du schéma (`Nuwave\Lighthouse\Schema\ResolverProvider`,
 * les champs de `Query` n'ont pas de résolution implicite contrairement aux
 * champs imbriqués). Garantit un schéma valide même sans aucun Content Type
 * exposé à l'API (`CompileGraphqlSchema` sans fragment actif).
 */
final class HealthCheckResolver
{
    public function ping(): bool
    {
        return true;
    }
}
