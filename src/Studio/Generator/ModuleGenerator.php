<?php

declare(strict_types=1);

namespace Baobab\Studio\Generator;

use Baobab\ContentTypes\Fields\FieldRegistry;
use Baobab\ContentTypes\Generator\GeneratedFileChecksums;
use Baobab\ContentTypes\Generator\MigrationTimestamp;
use Baobab\ContentTypes\Generator\StubRenderer;
use Baobab\Studio\Blueprint\ModuleBlueprint;
use Baobab\Studio\Relations\StudioRelationTargetResolver;
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
 * câblage nouveau nécessaire pour cette sous-passe). Ne génère pas encore
 * les widgets (étape 7, Pass A3c) — cette section reste absente de
 * `module.json` tant qu'elle n'est pas construite (`module.schema.json` ne
 * la rend pas obligatoire).
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
    ) {}

    /**
     * @return string Le "name" (vendor/slug) du module généré, à passer à InstallModule.
     */
    public function __invoke(ModuleBlueprint $blueprint): string
    {
        $name = (string) $blueprint->name();
        [$vendor, $slug] = explode('/', $name, 2);
        $namespace = Str::studly($vendor).'\\'.Str::studly($slug);
        $moduleDir = $this->moduleDir($name);
        $entities = $blueprint->entities();

        /** @var list<array{key: string, table: string}> $siblings */
        $siblings = array_map(
            static fn (array $entity): array => ['key' => $entity['key'], 'table' => $entity['table']],
            $entities,
        );

        $adminRouteFragments = [];
        $webRouteFragments = [];
        $apiRouteFragments = [];

        foreach ($entities as $entity) {
            $this->writeMigration($entity, $moduleDir, $siblings);
            $this->writeModel($entity, $moduleDir, $namespace, $siblings);
            $this->writePolicy($entity, $moduleDir, $namespace, $slug, $blueprint);

            $admin = $this->adminCrud->isEnabled($entity);
            $api = $this->apiCrud->isEnabled($entity);

            if ($admin || $api) {
                $this->checksums->write($moduleDir, "src/Http/Requests/{$entity['key']}Request.php", $this->adminCrud->request($entity, $namespace));
            }

            if ($admin) {
                $this->writeAdminCrud($entity, $moduleDir, $namespace, $slug);
                $adminRouteFragments[] = $this->adminCrud->routesFragment($entity, $namespace, $slug);
            }

            if ($this->frontCrud->isEnabled($entity)) {
                $this->writeFrontCrud($entity, $moduleDir, $namespace, $slug);
                $webRouteFragments[] = $this->frontCrud->routesFragment($entity, $namespace, $slug);
            }

            if ($api) {
                $this->writeApiCrud($entity, $moduleDir, $namespace, $slug);
                $apiRouteFragments[] = $this->apiCrud->routesFragment($entity, $namespace, $slug);
            }
        }

        if ($adminRouteFragments !== []) {
            $this->checksums->write($moduleDir, 'routes/admin.php', $this->routesFile($adminRouteFragments));
        }

        if ($webRouteFragments !== []) {
            $this->checksums->write($moduleDir, 'routes/web.php', $this->routesFile($webRouteFragments));
        }

        if ($apiRouteFragments !== []) {
            $this->checksums->write($moduleDir, 'routes/api.php', $this->routesFile($apiRouteFragments));
        }

        $this->writeHookListeners($blueprint, $moduleDir, $namespace);

        $providerClass = Str::studly($slug).'ServiceProvider';

        $this->checksums->write($moduleDir, 'module.json', $this->moduleJson($blueprint, $name, $namespace, $slug, $providerClass));

        $this->checksums->write($moduleDir, "src/Providers/{$providerClass}.php", (new StubRenderer)->render(StudioStubs::path('provider'), [
            'namespace' => $namespace,
            'key' => Str::studly($slug),
            'view_namespace' => $slug,
        ]));

        return $name;
    }

    /**
     * @param  array<string, mixed>  $entity
     */
    private function writeAdminCrud(array $entity, string $moduleDir, string $namespace, string $slug): void
    {
        $key = (string) $entity['key'];
        $viewPrefix = Str::snake(Str::plural($key));

        $this->checksums->write($moduleDir, "src/Http/Controllers/Admin/{$key}Controller.php", $this->adminCrud->controller($entity, $namespace, $slug));
        $this->checksums->write($moduleDir, "resources/views/{$viewPrefix}/index.blade.php", $this->adminCrud->indexView($entity, $slug));
        $this->checksums->write($moduleDir, "resources/views/{$viewPrefix}/form.blade.php", $this->adminCrud->formView($entity));
    }

    /**
     * @param  array<string, mixed>  $entity
     */
    private function writeFrontCrud(array $entity, string $moduleDir, string $namespace, string $slug): void
    {
        $key = (string) $entity['key'];
        $var = Str::camel($key);
        $viewPrefix = Str::snake(Str::plural($key));

        $this->checksums->write($moduleDir, "src/Http/Controllers/Front/{$key}Controller.php", $this->frontCrud->controller($entity, $namespace, $slug));
        $this->checksums->write($moduleDir, "resources/views/{$viewPrefix}/front-index.blade.php", $this->frontCrud->indexView($entity, $slug));
        $this->checksums->write($moduleDir, "resources/views/{$viewPrefix}/front-show.blade.php", $this->frontCrud->showView($entity, $slug, $var));
    }

    /**
     * @param  array<string, mixed>  $entity
     */
    private function writeApiCrud(array $entity, string $moduleDir, string $namespace, string $slug): void
    {
        $key = (string) $entity['key'];

        $this->checksums->write($moduleDir, "src/Http/Controllers/Api/{$key}Controller.php", $this->apiCrud->controller($entity, $namespace));
        $this->checksums->write($moduleDir, "src/Http/Resources/{$key}Resource.php", $this->apiCrud->resource($entity, $namespace));
    }

    /**
     * Écouteurs squelettes de `hooks.listens` — concept de module, pas
     * d'entité (contrairement aux CRUD admin/front/API), une seule passe
     * indépendante de la boucle `foreach ($entities as $entity)`.
     * `hooks.emits` n'a aucun fichier à générer (cf. docblock
     * `HookListenerGenerator`).
     */
    private function writeHookListeners(ModuleBlueprint $blueprint, string $moduleDir, string $namespace): void
    {
        foreach ($blueprint->hooksListened() as $hook => $className) {
            $this->checksums->write(
                $moduleDir,
                "src/Hooks/{$className}.php",
                $this->hookListeners->listener($hook, $className, $namespace),
            );
        }
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
     * @param  array<string, mixed>  $entity
     * @param  list<array{key: string, table: string}>  $siblings
     */
    private function writeMigration(array $entity, string $moduleDir, array $siblings): void
    {
        $table = (string) $entity['table'];
        $options = $entity['options'] ?? [];
        $uuid = (bool) ($options['uuid'] ?? false);

        $this->checksums->write(
            $moduleDir,
            'database/migrations/'.MigrationTimestamp::generate()."_create_{$table}_table.php",
            (new StubRenderer)->render(StudioStubs::path('migration'), [
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
        );

        foreach ($this->pivotMigrations($entity, $siblings) as $pivot) {
            $this->checksums->write($moduleDir, $pivot['filename'], $pivot['contents']);
        }
    }

    /**
     * @param  array<string, mixed>  $entity
     * @param  list<array{key: string, table: string}>  $siblings
     */
    private function writeModel(array $entity, string $moduleDir, string $namespace, array $siblings): void
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

        $this->checksums->write($moduleDir, "src/Models/{$key}.php", (new StubRenderer)->render(StudioStubs::path('model'), [
            'namespace' => $namespace,
            'key' => $key,
            'table_name' => (string) $entity['table'],
            'trait_imports' => implode("\n", $traitImports),
            'trait_use' => $traits === [] ? '' : '    use '.implode(', ', $traits).';',
            'fillable' => $this->fillableList($entity),
            'casts' => $this->castsList($entity),
            'relations' => $this->relationMethods($entity, $namespace, $siblings),
        ]));
    }

    /**
     * @param  array<string, mixed>  $entity
     */
    private function writePolicy(array $entity, string $moduleDir, string $namespace, string $slug, ModuleBlueprint $blueprint): void
    {
        $key = (string) $entity['key'];
        $var = Str::camel($key);
        $prefix = $this->permissionPrefix($slug, $key);

        $this->checksums->write($moduleDir, "src/Policies/{$key}Policy.php", (new StubRenderer)->render(StudioStubs::path('policy'), [
            'namespace' => $namespace,
            'key' => $key,
            'var' => $var,
            'permission_prefix' => $prefix,
            'custom_methods' => $this->customPolicyMethods($key, $var, $prefix, $blueprint),
        ]));
    }

    private function customPolicyMethods(string $key, string $var, string $prefix, ModuleBlueprint $blueprint): string
    {
        $methods = collect($blueprint->customPermissions())
            ->filter(fn (array $permission): bool => $permission['entity'] === $key)
            ->map(function (array $permission) use ($key, $var, $prefix): string {
                $method = Str::camel((string) $permission['key']);

                return "\n    public function {$method}(User \$user, {$key} \${$var}): bool\n    {\n        return \$user->can('{$prefix}.{$permission['key']}');\n    }\n";
            })
            ->implode('');

        return $methods;
    }

    private function permissionPrefix(string $slug, string $entityKey): string
    {
        return $slug.'.'.Str::snake(Str::plural($entityKey));
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
                $key = (string) $entity['key'];
                $prefix = $this->permissionPrefix($slug, $key);

                $permissions[] = ['key' => "{$prefix}.view", 'label' => "Voir : {$key}"];
                $permissions[] = ['key' => "{$prefix}.create", 'label' => "Créer : {$key}"];
                $permissions[] = ['key' => "{$prefix}.update", 'label' => "Modifier : {$key}"];
                $permissions[] = ['key' => "{$prefix}.delete", 'label' => "Supprimer : {$key}"];
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
     * @return list<array{filename: string, contents: string}>
     */
    private function pivotMigrations(array $entity, array $siblings): array
    {
        $ownerKey = (string) $entity['key'];
        $ownerTable = (string) $entity['table'];

        return array_values(array_filter(collect($this->manyToManyRelations($entity))
            ->map(fn (array $relation) => $this->relationDefinitions->pivotMigration(
                $relation,
                $ownerKey,
                $ownerTable,
                $this->relationTargets->resolve((string) $relation['target'], $siblings),
            ))
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
