<?php

declare(strict_types=1);

namespace Baobab\Console\Commands;

use Baobab\Branding\Actions\CompileDesignTokens;
use Illuminate\Console\Command;

/**
 * Force la recompilation de l'artefact de design tokens (spec 18 §4.2) —
 * patron `baobab:graphql:compile` : les déclencheurs automatiques (activation
 * de thème, sauvegarde du branding) couvrent l'usage courant, cette commande
 * sert au déploiement/CI (artefact absent d'un disque neuf).
 */
final class DesignTokensCompileCommand extends Command
{
    protected $signature = 'baobab:branding:compile';

    protected $description = 'Recompile l\'artefact CSS des design tokens depuis la cascade courante.';

    public function handle(CompileDesignTokens $compile): int
    {
        $path = $compile();

        $this->info("Design tokens compilés : {$path}");

        return self::SUCCESS;
    }
}
