<?php

declare(strict_types=1);

namespace Baobab\Api\GraphQL\Actions;

use Baobab\ContentTypes\Generator\ContentTypeModuleGenerator;
use Baobab\ContentTypes\Models\ContentType;
use Baobab\Facades\Hook;
use Illuminate\Support\Facades\File;
use Nuwave\Lighthouse\Schema\AST\ASTCache;

/**
 * Assemble le schéma GraphQL global (M7 point 3, spec 08 §3.2) — socle Core
 * (`resources/graphql/schema-core.graphql`) + le fragment de chaque Content
 * Type dont le module est actif et l'API activée (`ContentType::apiEnabled()`,
 * même règle que `ResolveApiContentType`, jamais un type désactivé n'apparaît
 * dans le schéma). Patron `CompileDesignTokens` (spec 18 §4.1) : écrit un
 * artefact, jamais de résolution à la volée au premier appel GraphQL.
 * Rejoué à `content-type:build`/`evolve` et à l'activation/désactivation d'un
 * module (`baobab.module.activated`/`.deactivated`) — voir
 * `BaobabServiceProvider::registerGraphqlSchemaCompilationListener()`.
 * Auto-cicatrisant : régénère le fragment/résolveur de chaque type éligible
 * avant de le lire, plutôt que de supposer qu'il existe déjà — couvre les
 * Content Types construits avant l'introduction de ce point.
 */
final class CompileGraphqlSchema
{
    public function __construct(private readonly ContentTypeModuleGenerator $generator) {}

    public function __invoke(): string
    {
        $path = (string) config('lighthouse.schema_path');

        $fragments = ContentType::query()
            ->whereNotNull('module_id')
            ->whereHas('module', fn ($query) => $query->where('status', 'active'))
            ->get()
            ->filter(fn (ContentType $contentType): bool => $contentType->apiEnabled())
            // Régénéré avant lecture, jamais juste lu tel quel : un Content
            // Type construit avant l'introduction de ce point (M7 point 3)
            // n'a ni fragment ni résolveur sur disque — bug réel découvert
            // en testant `Book` en environnement de développement réel,
            // jamais rencontré par les tests package (qui ne construisent
            // que des Content Types frais, déjà générés avec le fragment).
            // Écriture protégée par checksum : sans effet si déjà à jour.
            ->map(function (ContentType $contentType): string {
                $this->generator->regenerateGraphql($contentType);

                return $this->readFragment($contentType);
            })
            ->filter()
            ->implode("\n\n");

        $core = (string) File::get(__DIR__.'/../../../../resources/graphql/schema-core.graphql');

        File::ensureDirectoryExists(dirname($path));
        File::put($path, $core."\n\n".$fragments);

        // Le cache de schéma de Lighthouse (config `schema_cache`) est un AST
        // compilé du fichier ci-dessus, jamais relu automatiquement — sans
        // ce clear, un site avec le cache activé (défaut hors `local`,
        // `lighthouse.php`) continuerait de servir l'ancien schéma après
        // création/évolution/activation d'un Content Type. Service
        // `ASTCache` directement plutôt que `Artisan::call('lighthouse:
        // clear-cache')` : cette Action tourne aussi depuis les hooks
        // `baobab.module.*`, déclenchés au cœur de `module:activate`/
        // `module:deactivate` — `Illuminate\Console\Application::call()`
        // écrase inconditionnellement `$this->lastOutput` (même avec un
        // `OutputInterface` explicite passé en 3ᵉ argument), donc un
        // `Artisan::call()` imbriqué casse la capture de sortie de la
        // commande appelante (bug découvert en testant : `Artisan::output()`
        // de `module:activate` revenait vide plutôt que « activated »).
        app(ASTCache::class)->clear();

        Hook::action('baobab.graphql.compiled', $path);

        return $path;
    }

    private function readFragment(ContentType $contentType): string
    {
        $fragmentPath = $contentType->moduleDir()."/graphql/{$contentType->key}.graphql";

        return File::exists($fragmentPath) ? (string) File::get($fragmentPath) : '';
    }
}
