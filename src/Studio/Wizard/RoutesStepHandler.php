<?php

declare(strict_types=1);

namespace Baobab\Studio\Wizard;

use Baobab\Studio\Models\ModuleBlueprintDraft;
use Illuminate\Support\Str;

/**
 * Étape 4 — Routes & contrôleurs (spec-modules §5.2 étape 4, schéma
 * `module-blueprint.schema.json#/properties/entities/items/properties/routes`).
 *
 * Trois cases à cocher par entité, une par surface générée en Pass A2 : admin
 * (défaut coché), front et API REST (opt-in). Rien d'imbriqué, rien de
 * répétable — la saisie tient donc en notation de tableau HTML
 * (`routes[Car][admin]`) plutôt qu'en payload JSON comme les étapes 2 et 3.
 *
 * Les défauts repris ici sont **ceux des générateurs eux-mêmes**
 * (`AdminCrudGenerator::isEnabled()` et ses deux homologues) : une entité
 * déclarée avant cette étape et jamais passée par ce formulaire se génère
 * exactement comme l'écran l'annonce.
 */
final class RoutesStepHandler implements StudioStepHandler
{
    /** Défauts de `entity.routes.*`, alignés sur `{Admin,Front,Api}CrudGenerator::isEnabled()`. */
    private const DEFAULTS = ['admin' => true, 'front' => false, 'api' => false];

    public function number(): int
    {
        return 4;
    }

    public function label(): string
    {
        return __('baobab::admin.studio.steps.routes');
    }

    public function view(): string
    {
        return 'baobab::admin.studio.steps.routes';
    }

    public function rules(ModuleBlueprintDraft $draft): array
    {
        return [
            'routes' => ['nullable', 'array'],
            'routes.*' => ['array'],
            'routes.*.*' => ['in:0,1'],
        ];
    }

    /**
     * Écrit un bloc `routes` **explicite** sur chaque entité déclarée : une
     * case décochée n'est pas envoyée par le navigateur, donc l'absence de
     * clé vaut `false`, et non « garder l'ancienne valeur ». Une entité
     * absente de la soumission (créée dans un autre onglet entre-temps)
     * retombe sur les défauts des générateurs plutôt que sur trois `false`.
     */
    public function fill(array $validated, array $blueprint): array
    {
        /** @var array<string, mixed> $submitted */
        $submitted = $validated['routes'] ?? [];

        $entities = [];

        foreach ($blueprint['entities'] ?? [] as $entity) {
            $key = (string) ($entity['key'] ?? '');
            $surfaces = $submitted[$key] ?? null;

            $entity['routes'] = is_array($surfaces)
                ? [
                    'admin' => (bool) ($surfaces['admin'] ?? false),
                    'front' => (bool) ($surfaces['front'] ?? false),
                    'api' => (bool) ($surfaces['api'] ?? false),
                ]
                : self::DEFAULTS;

            $entities[] = $entity;
        }

        $blueprint['entities'] = $entities;

        return $blueprint;
    }

    public function initialValues(array $blueprint): array
    {
        $routes = [];

        foreach ($blueprint['entities'] ?? [] as $entity) {
            $key = (string) ($entity['key'] ?? '');

            if ($key === '') {
                continue;
            }

            $declared = $entity['routes'] ?? [];

            $routes[$key] = [
                'admin' => (bool) ($declared['admin'] ?? self::DEFAULTS['admin']),
                'front' => (bool) ($declared['front'] ?? self::DEFAULTS['front']),
                'api' => (bool) ($declared['api'] ?? self::DEFAULTS['api']),
            ];
        }

        return ['routes' => $routes];
    }

    /**
     * URIs et fichiers réellement produits par chaque surface — pas une
     * paraphrase : les préfixes viennent des trois `routesFragment()` de la
     * Pass A2 et des groupes de `ModuleServiceProvider`, le chemin d'admin de
     * la configuration effective de l'installation.
     */
    public function viewData(array $blueprint): array
    {
        $slug = (string) Str::of((string) ($blueprint['identity']['name'] ?? ''))->after('/');
        $adminPath = trim((string) config('baobab.admin.path', 'admin'), '/');

        $entities = [];

        foreach ($blueprint['entities'] ?? [] as $entity) {
            $key = (string) ($entity['key'] ?? '');

            if ($key === '') {
                continue;
            }

            $plural = Str::snake(Str::plural($key));
            $var = Str::camel($key);
            $base = "{$slug}/{$plural}";

            $entities[] = [
                'key' => $key,
                'surfaces' => [
                    'admin' => [
                        'uris' => ["{$adminPath}/{$base}", "{$adminPath}/{$base}/create", "{$adminPath}/{$base}/{{$var}}/edit"],
                        'files' => [
                            "src/Http/Controllers/Admin/{$key}Controller.php",
                            "src/Http/Requests/{$key}Request.php",
                            "resources/views/{$plural}/index.blade.php",
                            "resources/views/{$plural}/form.blade.php",
                            'routes/admin.php',
                        ],
                    ],
                    'front' => [
                        'uris' => [$base, "{$base}/{{$var}}"],
                        'files' => [
                            "src/Http/Controllers/Front/{$key}Controller.php",
                            "resources/views/{$plural}/front-index.blade.php",
                            "resources/views/{$plural}/front-show.blade.php",
                            'routes/web.php',
                        ],
                    ],
                    'api' => [
                        'uris' => ["api/v1/{$base}", "api/v1/{$base}/{{$var}}"],
                        'files' => [
                            "src/Http/Controllers/Api/{$key}Controller.php",
                            "src/Http/Resources/{$key}Resource.php",
                            "src/Http/Requests/{$key}Request.php",
                            'routes/api.php',
                        ],
                    ],
                ],
            ];
        }

        return ['routeEntities' => $entities];
    }

    /**
     * Cases à cocher brutes : `<x-baobab::field.checkbox>` n'est pas utilisé
     * ici (ses noms sont plats, pas en notation de tableau), donc `old()` ne
     * s'applique pas tout seul — sans cette réhydratation, un refus de la
     * cross-validation rendrait l'écran depuis le blueprint stocké et
     * re-cocherait ce que l'utilisateur venait de décocher.
     */
    public function valuesFromOldInput(array $old, array $values): array
    {
        $submitted = $old['routes'] ?? null;

        if (! is_array($submitted)) {
            return $values;
        }

        /** @var array<string, array<string, bool>> $routes */
        $routes = $values['routes'];

        foreach ($routes as $key => $surfaces) {
            $entitySurfaces = $submitted[$key] ?? [];

            foreach (array_keys($surfaces) as $surface) {
                $routes[$key][$surface] = is_array($entitySurfaces) && (bool) ($entitySurfaces[$surface] ?? false);
            }
        }

        return ['routes' => $routes];
    }
}
