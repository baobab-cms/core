<?php

declare(strict_types=1);

namespace Baobab\Studio\Generator;

use Baobab\ContentTypes\Exceptions\GeneratedFileConflictException;
use Baobab\ContentTypes\Fields\FieldRegistry;
use Baobab\ContentTypes\Generator\GeneratedFileChecksums;
use Baobab\ContentTypes\Generator\MigrationFilename;
use Baobab\ContentTypes\Generator\MigrationOrder;
use Baobab\ContentTypes\Generator\StubRenderer;
use Baobab\Studio\Blueprint\ModuleBlueprint;
use Baobab\Studio\Relations\StudioRelationTargetResolver;
use Baobab\Studio\Support\BlueprintPermissions;
use Illuminate\Support\Str;

/**
 * Transforme un `ModuleBlueprint` validé (spec-modules §5.2 étapes 1-3, §5.4)
 * en un vrai module Laravel sur disque : `module.json`, migrations, modèles,
 * policies, Service Provider — patron `ContentTypeModuleGenerator`, avec une
 * différence structurante : un blueprint Studio décrit **N entités** dans un
 * seul module (contrairement à un Content Type, toujours une seule entité
 * par module généré), donc le namespace racine vient de l'identité du module
 * (`vendor/slug` → `Vendor\Slug`) et non d'une clé d'entité.
 *
 * Périmètre couvert à ce stade : migration + modèle + permissions + policy
 * par entité (Pass A1, spec-modules §5.2 étapes 2/3/5) ; contrôleurs/vues/
 * routes admin (CRUD complet), front (`index`/`show`, opt-in) et API REST
 * (CRUD complet, opt-in) par entité, à la carte via `entity.routes.{admin,
 * front,api}` (Pass A2, étape 4) ; hooks émis/écoutés au niveau module,
 * écouteurs squelettes générés (Pass A3a, étape 8) ; entrées de menu admin,
 * recopiées telles quelles (Pass A3b, étape 6 — déjà pleinement consommées
 * côté Core, `InstallModule::persistMenuItems()`/`SidebarBuilder`, aucun
 * câblage nouveau nécessaire pour cette sous-passe) ; classe Widget + vue
 * Blade par widget déclaré (Pass A3c, étape 7 — contrairement aux menus,
 * ce bloc n'était consommé par aucun mécanisme Core existant :
 * `bootstrapActiveModules()` a dû être complété pour enregistrer les
 * widgets d'un module actif dans `WidgetRegistry`). Reste, pour clore
 * Pass A : la CLI `baobab:module:build` qui assemble A1+A2+A3.
 */
final class ModuleGenerator
{
    public function __construct(
        private readonly GeneratedFileChecksums $checksums,
        private readonly FieldRegistry $fields,
        private readonly StudioRelationTargetResolver $relationTargets,
        private readonly StudioRelationDefinitionGenerator $relationDefinitions,
        private readonly AdminCrudGenerator $adminCrud,
        private readonly FrontCrudGenerator $frontCrud,
        private readonly ApiCrudGenerator $apiCrud,
        private readonly HookListenerGenerator $hookListeners,
        private readonly WidgetGenerator $widgets,
    ) {}

    /**
     * Génération **stricte** : le moindre fichier modifié à la main fait
     * échouer l'appel sans rien écrire.
     *
     * C'est le contrat de la CLI `module:build`, inchangé depuis la Pass A, et
     * il doit le rester : au terminal il n'y a ni diff ni case à cocher, donc
     * sauter un fichier en silence serait le symétrique exact de l'écrasement
     * silencieux qu'on interdit — l'utilisateur croirait avoir régénéré. Le
     * wizard, lui, passe par `write()`, qui sait sauter parce qu'il a d'abord
     * montré ce qu'il sautait.
     *
     * @return string Le "name" (vendor/slug) du module généré, à passer à InstallModule.
     *
     * @throws GeneratedFileConflictException
     */
    public function __invoke(ModuleBlueprint $blueprint): string
    {
        $moduleDir = $this->moduleDir((string) $blueprint->name());

        foreach ($this->plan($blueprint) as $relativePath => $contents) {
            $this->checksums->write($moduleDir, $relativePath, $contents);
        }

        return (string) $blueprint->name();
    }

    /**
     * Écrit le plan et **retourne ce qui a été écrit et ce qui a été laissé
     * intact**. Le produit peut ainsi dire ce qu'il a fait au lieu d'afficher
     * un « Terminé ✓ » (direction visuelle §11).
     *
     * Règle appliquée, celle de la spec 01 §5.4 : **régénérer ce qui est sûr,
     * ne jamais écraser silencieusement une modification manuelle**. Un
     * fichier en conflit qui ne figure pas dans `$overwrite` est donc
     * **sauté** — pas une raison d'abandonner toute la régénération. Le
     * premier jet de la Pass C bloquait l'ensemble, ce qui contredisait à la
     * fois la spec et le texte de l'écran de conflit.
     *
     * `$overwrite` liste les chemins dont l'utilisateur a explicitement
     * accepté l'écrasement après avoir vu le diff : eux seuls passent outre
     * l'anti-écrasement, fichier par fichier — jamais un `force` global, qui
     * reviendrait à désarmer la protection entière pour un seul conflit
     * accepté.
     *
     * @param  list<string>  $overwrite
     * @return array{written: list<string>, skipped: list<string>}
     */
    public function write(ModuleBlueprint $blueprint, array $overwrite = []): array
    {
        $moduleDir = $this->moduleDir((string) $blueprint->name());

        $written = [];
        $skipped = [];

        foreach ($this->plan($blueprint) as $relativePath => $contents) {
            $accepted = in_array($relativePath, $overwrite, true);

            if (! $accepted && $this->checksums->conflicts($moduleDir, $relativePath)) {
                $skipped[] = $relativePath;

                continue;
            }

            $this->checksums->write($moduleDir, $relativePath, $contents, $accepted);
            $written[] = $relativePath;
        }

        return ['written' => $written, 'skipped' => $skipped];
    }

    /**
     * Chemins du plan qui écraseraient une modification manuelle, avec les
     * deux versions à comparer. Vide tant que le module n'a jamais été généré.
     *
     * @return array<string, array{disk: string, generated: string}>
     */
    public function conflicts(ModuleBlueprint $blueprint): array
    {
        $moduleDir = $this->moduleDir((string) $blueprint->name());

        $conflicts = [];

        foreach ($this->plan($blueprint) as $relativePath => $contents) {
            if ($this->checksums->conflicts($moduleDir, $relativePath)) {
                $conflicts[$relativePath] = [
                    'disk' => (string) file_get_contents($moduleDir.'/'.$relativePath),
                    'generated' => $contents,
                ];
            }
        }

        ksort($conflicts);

        return $conflicts;
    }

    /**
     * Carte complète `chemin relatif → contenu` de ce que la génération
     * écrirait, **sans rien écrire**.
     *
     * Cœur unique extrait en Pass B5 : l'étape 9 du wizard prévisualise
     * l'arborescence et le contenu de chaque fichier, et `__invoke()` ne fait
     * plus que dérouler cette même carte à travers `GeneratedFileChecksums`.
     * Sans cette séparation, l'aperçu aurait été une seconde implémentation du
     * rendu — la première à mentir dès qu'un stub change (même raisonnement
     * que `BlueprintPermissions` en Pass B3).
     *
     * **Une seule chose n'est pas reproductible d'un appel à l'autre** : le
     * nom d'une migration porte un horodatage et un suffixe aléatoire
     * (`MigrationTimestamp`), donc l'aperçu et l'écriture ne montrent pas la
     * même seconde. L'écran le dit plutôt que de faire semblant. **Restreint
     * le 10 août 2026** : ça ne vaut plus que pour la toute première
     * génération. Dès qu'un module existe sur disque, `MigrationFilename`
     * réutilise le nom déjà écrit — sans quoi chaque régénération laissait un
     * doublon de la migration de création, que l'installation suivante
     * rejouait sur une table déjà là (suivi n° 116).
     *
     * @return array<string, string>
     */
    public function plan(ModuleBlueprint $blueprint): array
    {
        $name = (string) $blueprint->name();
        [$vendor, $slug] = explode('/', $name, 2);
        $namespace = Str::studly($vendor).'\\'.Str::studly($slug);
        $entities = $blueprint->entities();

        /** @var list<array{key: string, table: string}> $siblings */
        $siblings = array_map(
            static fn (array $entity): array => ['key' => $entity['key'], 'table' => $entity['table']],
            $entities,
        );

        // Rang de chaque table dans l'ordre de création, calculé une fois pour
        // tout le module : c'est lui qui nomme les migrations, donc lui qui
        // décide de leur ordre d'exécution et de leur identité (suivi n° 120).
        $ranks = $this->tableRanks($entities, $siblings);

        $files = [];
        $adminRouteFragments = [];
        $webRouteFragments = [];
        $apiRouteFragments = [];

        foreach ($entities as $entity) {
            $files = [
                ...$files,
                ...$this->planMigration($entity, $siblings, $this->moduleDir($name), $ranks),
                ...$this->planModel($entity, $namespace, $siblings),
                ...$this->planPolicy($entity, $namespace, $slug, $blueprint),
            ];

            $admin = $this->adminCrud->isEnabled($entity);
            $api = $this->apiCrud->isEnabled($entity);

            if ($admin || $api) {
                $files["src/Http/Requests/{$entity['key']}Request.php"] = $this->adminCrud->request($entity, $namespace);
            }

            if ($admin) {
                $files = [...$files, ...$this->planAdminCrud($entity, $namespace, $slug)];
                $adminRouteFragments[] = $this->adminCrud->routesFragment($entity, $namespace, $slug);
            }

            if ($this->frontCrud->isEnabled($entity)) {
                $files = [...$files, ...$this->planFrontCrud($entity, $namespace, $slug)];
                $webRouteFragments[] = $this->frontCrud->routesFragment($entity, $namespace, $slug);
            }

            if ($api) {
                $files = [...$files, ...$this->planApiCrud($entity, $namespace)];
                $apiRouteFragments[] = $this->apiCrud->routesFragment($entity, $namespace, $slug);
            }
        }

        if ($adminRouteFragments !== []) {
            $files['routes/admin.php'] = $this->routesFile($adminRouteFragments);
        }

        if ($webRouteFragments !== []) {
            $files['routes/web.php'] = $this->routesFile($webRouteFragments);
        }

        if ($apiRouteFragments !== []) {
            $files['routes/api.php'] = $this->routesFile($apiRouteFragments);
        }

        $files = [
            ...$files,
            ...$this->planHookListeners($blueprint, $namespace),
            ...$this->planWidgets($blueprint, $namespace, $slug),
        ];

        $providerClass = Str::studly($slug).'ServiceProvider';

        $files['module.json'] = $this->moduleJson($blueprint, $name, $namespace, $slug, $providerClass);

        $files["src/Providers/{$providerClass}.php"] = (new StubRenderer)->render(StudioStubs::path('provider'), [
            'namespace' => $namespace,
            'key' => Str::studly($slug),
            'view_namespace' => $slug,
        ]);

        return $files;
    }

    /**
     * @param  array<string, mixed>  $entity
     * @return array<string, string>
     */
    private function planAdminCrud(array $entity, string $namespace, string $slug): array
    {
        $key = (string) $entity['key'];
        $viewPrefix = Str::snake(Str::plural($key));

        return [
            "src/Http/Controllers/Admin/{$key}Controller.php" => $this->adminCrud->controller($entity, $namespace, $slug),
            "resources/views/{$viewPrefix}/index.blade.php" => $this->adminCrud->indexView($entity, $slug),
            "resources/views/{$viewPrefix}/form.blade.php" => $this->adminCrud->formView($entity),
        ];
    }

    /**
     * @param  array<string, mixed>  $entity
     * @return array<string, string>
     */
    private function planFrontCrud(array $entity, string $namespace, string $slug): array
    {
        $key = (string) $entity['key'];
        $var = Str::camel($key);
        $viewPrefix = Str::snake(Str::plural($key));

        return [
            "src/Http/Controllers/Front/{$key}Controller.php" => $this->frontCrud->controller($entity, $namespace, $slug),
            "resources/views/{$viewPrefix}/front-index.blade.php" => $this->frontCrud->indexView($entity, $slug),
            "resources/views/{$viewPrefix}/front-show.blade.php" => $this->frontCrud->showView($entity, $slug, $var),
        ];
    }

    /**
     * @param  array<string, mixed>  $entity
     * @return array<string, string>
     */
    private function planApiCrud(array $entity, string $namespace): array
    {
        $key = (string) $entity['key'];

        return [
            "src/Http/Controllers/Api/{$key}Controller.php" => $this->apiCrud->controller($entity, $namespace),
            "src/Http/Resources/{$key}Resource.php" => $this->apiCrud->resource($entity, $namespace),
        ];
    }

    /**
     * Écouteurs squelettes de `hooks.listens` — concept de module, pas
     * d'entité (contrairement aux CRUD admin/front/API), une seule passe
     * indépendante de la boucle `foreach ($entities as $entity)`.
     * `hooks.emits` n'a aucun fichier à générer (cf. docblock
     * `HookListenerGenerator`).
     *
     * @return array<string, string>
     */
    private function planHookListeners(ModuleBlueprint $blueprint, string $namespace): array
    {
        $files = [];

        foreach ($blueprint->hooksListened() as $hook => $className) {
            $files["src/Hooks/{$className}.php"] = $this->hookListeners->listener($hook, $className, $namespace);
        }

        return $files;
    }

    /**
     * Classe Widget + vue Blade par widget déclaré — concept de module, pas
     * d'entité, une seule passe indépendante de la boucle `foreach
     * ($entities as $entity)` (patron `planHookListeners`).
     *
     * @return array<string, string>
     */
    private function planWidgets(ModuleBlueprint $blueprint, string $namespace, string $slug): array
    {
        $files = [];

        foreach ($blueprint->widgets() as $widget) {
            $files["src/Widgets/{$widget['class_name']}.php"] = $this->widgets->widgetClass($widget, $namespace, $slug);
            $files["resources/views/widgets/{$this->widgets->viewName($widget)}.blade.php"] = $this->widgets->widgetView($widget);
        }

        return $files;
    }

    /**
     * @param  list<string>  $fragments
     */
    private function routesFile(array $fragments): string
    {
        $body = implode("\n\n", $fragments);

        return <<<PHP
        <?php

        declare(strict_types=1);

        use Illuminate\Support\Facades\Route;

        {$body}

        PHP;
    }

    public function moduleDir(string $name): string
    {
        $dirSlug = Str::slug(str_replace('/', '-', $name));

        return rtrim((string) config('baobab.studio.modules_path'), '/')."/{$dirSlug}";
    }

    /**
     * Le rang de chaque table du module dans l'ordre de création : une table
     * référencée précède celle qui la référence, et le pivot d'un `many_to_many`
     * passe après ses deux côtés (suivi n° 120).
     *
     * Les cibles hors du module (Content Type existant, modèle du Core) ne
     * figurent pas dans le graphe : leur table existe déjà, il n'y a rien à
     * ordonner avant elle. Une entité qui se référence elle-même non plus — sa
     * colonne naît dans le même `CREATE TABLE` que la clé qu'elle vise.
     *
     * @param  list<array<string, mixed>>  $entities
     * @param  list<array{key: string, table: string}>  $siblings
     * @return array<string, int>
     */
    private function tableRanks(array $entities, array $siblings): array
    {
        $moduleTables = array_map(static fn (array $sibling): string => $sibling['table'], $siblings);

        /** @var array<string, list<string>> $graph */
        $graph = array_fill_keys($moduleTables, []);

        foreach ($entities as $entity) {
            $table = (string) $entity['table'];

            foreach ($this->ownColumnRelations($entity) as $relation) {
                $target = $this->relationTargets->resolve((string) $relation['target'], $siblings);

                // Une cible polymorphique n'a pas de table unique à attendre :
                // elle n'impose aucun ordre.
                if (is_string($target['table'])) {
                    $graph[$table][] = $target['table'];
                }
            }

            foreach ($this->manyToManyRelations($entity) as $relation) {
                $target = $this->relationTargets->resolve((string) $relation['target'], $siblings);
                $pivot = $this->relationDefinitions->pivotTableName((string) $entity['key'], $target['key']);

                $graph[$pivot] = array_filter(
                    [$table, $target['table']],
                    static fn (?string $dependency): bool => is_string($dependency),
                );
            }
        }

        $ranks = [];

        foreach (MigrationOrder::sort($graph) as $position => $table) {
            $ranks[$table] = $position + 1;
        }

        return $ranks;
    }

    /**
     * @param  array<string, mixed>  $entity
     * @param  list<array{key: string, table: string}>  $siblings
     * @param  array<string, int>  $ranks
     * @return array<string, string>
     */
    private function planMigration(array $entity, array $siblings, string $moduleDir, array $ranks): array
    {
        $table = (string) $entity['table'];
        $options = $entity['options'] ?? [];
        $uuid = (bool) ($options['uuid'] ?? false);

        $files = [
            MigrationFilename::create($moduleDir, $table, $ranks[$table] ?? null) => (new StubRenderer)->render(StudioStubs::path('migration'), [
                'table_name' => $table,
                'id_column' => $uuid
                    ? "            \$table->uuid('id')->primary();"
                    : '            $table->id();',
                'field_columns' => $this->fieldColumns($entity),
                'relation_columns' => $this->relationColumns($entity, $siblings),
                'timestamps_column' => ($options['timestamps'] ?? true)
                    ? '            $table->timestamps();'
                    : '',
                'soft_deletes_column' => ($options['soft_deletes'] ?? false)
                    ? '            $table->softDeletes();'
                    : '',
            ]),
        ];

        foreach ($this->pivotMigrations($entity, $siblings, $moduleDir, $ranks) as $pivot) {
            $files[$pivot['filename']] = $pivot['contents'];
        }

        return $files;
    }

    /**
     * @param  array<string, mixed>  $entity
     * @param  list<array{key: string, table: string}>  $siblings
     * @return array<string, string>
     */
    private function planModel(array $entity, string $namespace, array $siblings): array
    {
        $key = (string) $entity['key'];
        $options = $entity['options'] ?? [];
        $softDeletes = (bool) ($options['soft_deletes'] ?? false);
        $uuid = (bool) ($options['uuid'] ?? false);

        $traits = array_filter([
            $softDeletes ? 'SoftDeletes' : null,
            $uuid ? 'HasUuids' : null,
        ]);

        $traitImports = array_filter([
            $softDeletes ? 'use Illuminate\\Database\\Eloquent\\SoftDeletes;' : null,
            $uuid ? 'use Illuminate\\Database\\Eloquent\\Concerns\\HasUuids;' : null,
        ]);

        return ["src/Models/{$key}.php" => (new StubRenderer)->render(StudioStubs::path('model'), [
            'namespace' => $namespace,
            'key' => $key,
            'table_name' => (string) $entity['table'],
            'trait_imports' => implode("\n", $traitImports),
            'trait_use' => $traits === [] ? '' : '    use '.implode(', ', $traits).';',
            'fillable' => $this->fillableList($entity),
            'casts' => $this->castsList($entity),
            'relations' => $this->relationMethods($entity, $namespace, $siblings),
        ])];
    }

    /**
     * @param  array<string, mixed>  $entity
     * @return array<string, string>
     */
    private function planPolicy(array $entity, string $namespace, string $slug, ModuleBlueprint $blueprint): array
    {
        $key = (string) $entity['key'];
        $var = Str::camel($key);
        $prefix = $this->permissionPrefix($slug, $key);

        return ["src/Policies/{$key}Policy.php" => (new StubRenderer)->render(StudioStubs::path('policy'), [
            'namespace' => $namespace,
            'key' => $key,
            'var' => $var,
            'permission_prefix' => $prefix,
            'custom_methods' => $this->customPolicyMethods($key, $var, $slug, $blueprint),
        ])];
    }

    /**
     * Méthodes de policy supplémentaires, une par permission personnalisée de
     * l'entité. Le nom de méthode et la chaîne de permission viennent de
     * `BlueprintPermissions` — la même source que l'aperçu de l'étape 5 du
     * wizard, qui ne promet donc jamais autre chose que ce qui est écrit ici.
     */
    private function customPolicyMethods(string $key, string $var, string $slug, ModuleBlueprint $blueprint): string
    {
        return collect(BlueprintPermissions::policyMethods($slug, $key, $blueprint->customPermissions()))
            ->filter(fn (array $method): bool => $method['custom'])
            ->map(fn (array $method): string => "\n    public function {$method['method']}(User \$user, {$key} \${$var}): bool\n    {\n        return \$user->can('{$method['permission']}');\n    }\n")
            ->implode('');
    }

    private function permissionPrefix(string $slug, string $entityKey): string
    {
        return BlueprintPermissions::prefix($slug, $entityKey);
    }

    private function moduleJson(ModuleBlueprint $blueprint, string $name, string $namespace, string $slug, string $providerClass): string
    {
        $identity = $blueprint->identity();

        $manifest = array_filter([
            'name' => $name,
            'title' => $identity['title'] ?? $slug,
            'description' => $identity['description'] ?? null,
            'version' => $identity['version'] ?? '1.0.0',
            'type' => 'module',
            'icon' => $identity['icon'] ?? null,
            'authors' => $identity['authors'] ?? null,
            'provider' => "{$namespace}\\Providers\\{$providerClass}",
            'autoload' => [
                'psr-4' => ["{$namespace}\\" => 'src/'],
            ],
            'permissions' => $this->permissions($blueprint, $slug),
            'hooks' => $this->hooksBlock($blueprint, $namespace),
            'menus' => $this->menusBlock($blueprint),
            'widgets' => $this->widgetsBlock($blueprint, $namespace),
        ], static fn (mixed $value): bool => $value !== null);

        return (string) json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Recopie directe, aucune transformation : contrairement aux hooks (nom
     * court de classe → FQCN), une entrée de menu déclarée au blueprint a
     * déjà la forme exacte attendue par `module.schema.json`/`ModuleManifest::adminMenuItems()`
     * (route/permission/icône déjà des chaînes complètes saisies par
     * l'utilisateur).
     *
     * @return array{admin: list<array<string, mixed>>}|null
     */
    private function menusBlock(ModuleBlueprint $blueprint): ?array
    {
        $admin = $blueprint->adminMenuItems();

        return $admin === [] ? null : ['admin' => $admin];
    }

    /**
     * FQCN résolu par widget, comme les hooks — pas une recopie brute comme
     * les menus (le blueprint ne porte que le nom court de classe).
     *
     * @return list<array{key: string, class: string}>|null
     */
    private function widgetsBlock(ModuleBlueprint $blueprint, string $namespace): ?array
    {
        $widgets = $blueprint->widgets();

        if ($widgets === []) {
            return null;
        }

        return array_map(
            fn (array $widget): array => [
                'key' => (string) $widget['key'],
                'class' => "{$namespace}\\Widgets\\{$widget['class_name']}",
            ],
            $widgets,
        );
    }

    /**
     * @return array{emits?: list<string>, listens?: array<string, string>}|null
     */
    private function hooksBlock(ModuleBlueprint $blueprint, string $namespace): ?array
    {
        $emits = $blueprint->hooksEmitted();
        $listens = $blueprint->hooksListened();

        if ($emits === [] && $listens === []) {
            return null;
        }

        return array_filter([
            'emits' => $emits === [] ? null : $emits,
            'listens' => $listens === [] ? null : array_map(
                fn (string $className): string => "{$namespace}\\Hooks\\{$className}",
                $listens,
            ),
        ], static fn (mixed $value): bool => $value !== null);
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    private function permissions(ModuleBlueprint $blueprint, string $slug): array
    {
        $permissions = [];

        if ($blueprint->autoCrudEnabled()) {
            foreach ($blueprint->entities() as $entity) {
                foreach (BlueprintPermissions::crudEntries($slug, (string) $entity['key']) as $entry) {
                    $permissions[] = $entry;
                }
            }
        }

        foreach ($blueprint->customPermissions() as $custom) {
            $prefix = $this->permissionPrefix($slug, (string) $custom['entity']);
            $permissions[] = ['key' => "{$prefix}.{$custom['key']}", 'label' => (string) $custom['label']];
        }

        return $permissions;
    }

    /**
     * @param  array<string, mixed>  $entity
     */
    private function fieldColumns(array $entity): string
    {
        return collect((array) ($entity['fields'] ?? []))
            ->map(function (array $field): string {
                $fieldType = $this->fields->resolve($field['type']);

                return $fieldType->columnDefinition($field['key'], $field['options'] ?? []);
            })
            ->filter(fn (string $column): bool => $column !== '')
            ->map(fn (string $column): string => '            '.$column)
            ->implode("\n");
    }

    /**
     * @param  array<string, mixed>  $entity
     * @param  list<array{key: string, table: string}>  $siblings
     */
    private function relationColumns(array $entity, array $siblings): string
    {
        return collect($this->ownColumnRelations($entity))
            ->map(fn (array $relation): string => $this->relationDefinitions->columnsDefinition(
                $relation,
                $this->relationTargets->resolve((string) $relation['target'], $siblings),
            ))
            ->implode("\n");
    }

    /**
     * @param  array<string, mixed>  $entity
     */
    private function castsList(array $entity): string
    {
        return collect((array) ($entity['fields'] ?? []))
            ->map(function (array $field): ?string {
                $fieldType = $this->fields->resolve($field['type']);
                $cast = $fieldType->cast($field['options'] ?? []);

                return $cast === null ? null : "            '{$field['key']}' => '{$cast}',";
            })
            ->filter()
            ->implode("\n");
    }

    /**
     * @param  array<string, mixed>  $entity
     */
    private function fillableList(array $entity): string
    {
        $columns = [];

        foreach ((array) ($entity['fields'] ?? []) as $field) {
            $fieldType = $this->fields->resolve($field['type']);

            if ($fieldType->columnDefinition($field['key'], $field['options'] ?? []) === '') {
                continue;
            }

            $columns[] = $field['key'];
        }

        foreach ($this->ownColumnRelations($entity) as $relation) {
            if ($relation['type'] === 'polymorphic') {
                $columns[] = "{$relation['key']}_type";
            }

            $columns[] = "{$relation['key']}_id";
        }

        return collect($columns)
            ->map(fn (string $column): string => "        '{$column}',")
            ->implode("\n");
    }

    /**
     * @param  array<string, mixed>  $entity
     * @param  list<array{key: string, table: string}>  $siblings
     */
    private function relationMethods(array $entity, string $namespace, array $siblings): string
    {
        $ownerKey = (string) $entity['key'];

        return collect((array) ($entity['relations'] ?? []))
            ->map(fn (array $relation): string => $this->relationDefinitions->eloquentMethod(
                $relation,
                $this->relationTargets->resolve((string) $relation['target'], $siblings),
                $namespace,
                $ownerKey,
            ))
            ->implode("\n\n");
    }

    /**
     * @param  array<string, mixed>  $entity
     * @param  list<array{key: string, table: string}>  $siblings
     * @param  array<string, int>  $ranks
     * @return list<array{filename: string, contents: string}>
     */
    private function pivotMigrations(array $entity, array $siblings, string $moduleDir, array $ranks): array
    {
        $ownerKey = (string) $entity['key'];
        $ownerTable = (string) $entity['table'];

        return array_values(array_filter(collect($this->manyToManyRelations($entity))
            ->map(function (array $relation) use ($ownerKey, $ownerTable, $siblings, $moduleDir, $ranks): ?array {
                $target = $this->relationTargets->resolve((string) $relation['target'], $siblings);
                $pivot = $this->relationDefinitions->pivotTableName($ownerKey, $target['key']);

                return $this->relationDefinitions->pivotMigration(
                    $relation,
                    $ownerKey,
                    $ownerTable,
                    $target,
                    $moduleDir,
                    $ranks[$pivot] ?? null,
                );
            })
            ->all()));
    }

    /**
     * Relations qui ajoutent une colonne côté déclarant (tout sauf many_to_many).
     *
     * @param  array<string, mixed>  $entity
     * @return list<array<string, mixed>>
     */
    private function ownColumnRelations(array $entity): array
    {
        return array_values(array_filter(
            (array) ($entity['relations'] ?? []),
            fn (array $relation): bool => $relation['type'] !== 'many_to_many',
        ));
    }

    /**
     * @param  array<string, mixed>  $entity
     * @return list<array<string, mixed>>
     */
    private function manyToManyRelations(array $entity): array
    {
        return array_values(array_filter(
            (array) ($entity['relations'] ?? []),
            fn (array $relation): bool => $relation['type'] === 'many_to_many',
        ));
    }
}
