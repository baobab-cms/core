<?php

declare(strict_types=1);

namespace Baobab\Console\Commands;

use Baobab\Api\GraphQL\Actions\CompileGraphqlSchema;
use Illuminate\Console\Command;

/**
 * Force la recompilation du schéma GraphQL (M7 point 3, spec 08 §3.2) —
 * patron `seo:sitemap` : les déclencheurs automatiques (`content-type:build`/
 * `evolve`, activation/désactivation de module) couvrent l'usage courant,
 * cette commande sert au déploiement/CI (artefact absent d'un disque neuf).
 */
final class GraphqlCompileCommand extends Command
{
    protected $signature = 'baobab:graphql:compile';

    protected $description = 'Recompile le schéma GraphQL global depuis les fragments des Content Types actifs.';

    public function handle(CompileGraphqlSchema $compile): int
    {
        $path = $compile();

        $this->info("Schéma GraphQL compilé : {$path}");

        return self::SUCCESS;
    }
}
