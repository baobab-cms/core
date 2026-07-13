<?php

declare(strict_types=1);

namespace Baobab\ContentTypes\Generator;

use Baobab\ContentTypes\Fields\FieldRegistry;
use Baobab\ContentTypes\Models\ContentType;
use Baobab\ContentTypes\Relations\RelationDefinitionGenerator;
use Baobab\ContentTypes\Relations\RelationTargetResolver;
use Illuminate\Support\Str;

/**
 * Transforme un ContentType persisté (blueprint validé, table_name dérivé —
 * M3 point 1a) en un vrai module Laravel sur disque : module.json, migration,
 * modèle, policy, provider (spec 02 §1.2). Ne fait tourner ni la migration ni
 * l'installation — c'est le rôle de BuildContentType, qui compose ce
 * générateur avec InstallModule/ActivateModule (M1, inchangés).
 */
final class ContentTypeModuleGenerator
{
    public function __construct(
        private readonly StubRenderer $renderer,
        private readonly GeneratedFileChecksums $checksums,
        private readonly FieldRegistry $fields,
        private readonly RelationTargetResolver $relationTargets,
        private readonly RelationDefinitionGenerator $relationDefinitions,
    ) {}

    /**
     * @return string Le "name" (vendor/slug) du module généré, à passer à InstallModule.
     */
    public function __invoke(ContentType $contentType): string
    {
        $key = $contentType->key;
        $dirSlug = Str::kebab(Str::plural($key));
        $moduleName = "content-types/{$dirSlug}";
        $namespace = "Modules\\{$key}";
        $permissionPrefix = 'content.'.Str::snake($key);
        $moduleDir = $contentType->moduleDir();

        $this->checksums->write($moduleDir, 'module.json', $this->moduleJson(
            $contentType,
            $moduleName,
            $namespace,
            $permissionPrefix,
            $dirSlug,
        ));

        $this->checksums->write(
            $moduleDir,
            'database/migrations/'.MigrationTimestamp::generate()."_create_{$contentType->table_name}_table.php",
            $this->renderer->render(StubRenderer::stubPath('migration'), [
                'table_name' => $contentType->table_name,
                'unpublish_at_column' => $contentType->unpublishAtEnabled()
                    ? "            \$table->timestamp('unpublish_at')->nullable();\n"
                    : '',
                'slug_column' => $contentType->is_addressable
                    ? "            \$table->string('slug')->unique();\n"
                    : '',
                'field_columns' => $this->fieldColumns($contentType),
                'relation_columns' => $this->relationColumns($contentType),
            ]),
        );

        foreach ($this->pivotMigrations($contentType) as $pivot) {
            $this->checksums->write($moduleDir, $pivot['filename'], $pivot['contents']);
        }

        $this->writeModel($contentType, $moduleDir);

        $this->checksums->write($moduleDir, "src/Policies/{$key}Policy.php", $this->renderer->render(StubRenderer::stubPath('policy'), [
            'namespace' => $namespace,
            'key' => $key,
            'var' => Str::camel($key),
            'permission_prefix' => $permissionPrefix,
        ]));

        $this->checksums->write($moduleDir, "src/Providers/{$key}ServiceProvider.php", $this->renderer->render(StubRenderer::stubPath('provider'), [
            'namespace' => $namespace,
            'key' => $key,
        ]));

        return $moduleName;
    }

    /**
     * Régénère uniquement le modèle Eloquent (fillable/casts/relations à
     * jour) — utilisé par EvolveContentType (M3 point 4) après une migration
     * incrémentale, sans retoucher module.json/policy/provider ni la
     * migration de création d'origine. Passe par le même anti-écrasement par
     * checksum que le reste du générateur.
     */
    public function regenerateModel(ContentType $contentType): void
    {
        $this->writeModel($contentType, $contentType->moduleDir());
    }

    private function writeModel(ContentType $contentType, string $moduleDir): void
    {
        $key = $contentType->key;

        $this->checksums->write($moduleDir, "src/Models/{$key}.php", $this->renderer->render(StubRenderer::stubPath('model'), [
            'namespace' => "Modules\\{$key}",
            'key' => $key,
            'table_name' => $contentType->table_name,
            'fillable' => $this->fillableList($contentType),
            'unpublish_at_cast' => $contentType->unpublishAtEnabled()
                ? "            'unpublish_at' => 'datetime',"
                : '',
            'casts' => $this->castsList($contentType),
            'relations' => $this->relationMethods($contentType),
        ]));
    }

    private function moduleJson(ContentType $contentType, string $moduleName, string $namespace, string $permissionPrefix, string $dirSlug): string
    {
        $key = $contentType->key;
        $label = $contentType->blueprint['label'] ?? ['singular' => $key, 'plural' => $key];

        $manifest = [
            'name' => $moduleName,
            'title' => $label['plural'],
            'description' => "Content Type généré : {$label['singular']} / {$label['plural']}.",
            'version' => '1.0.0',
            'type' => 'content-type',
            'provider' => "{$namespace}\\Providers\\{$key}ServiceProvider",
            'autoload' => [
                'psr-4' => ["{$namespace}\\" => 'src/'],
            ],
            'permissions' => [
                ['key' => "{$permissionPrefix}.view", 'label' => "Voir : {$label['plural']}"],
                ['key' => "{$permissionPrefix}.create", 'label' => "Créer : {$label['singular']}"],
                ['key' => "{$permissionPrefix}.update", 'label' => "Modifier (les siens) : {$label['singular']}"],
                ['key' => "{$permissionPrefix}.update_any", 'label' => "Modifier (tous) : {$label['plural']}"],
                ['key' => "{$permissionPrefix}.delete", 'label' => "Supprimer (les siens) : {$label['singular']}"],
                ['key' => "{$permissionPrefix}.delete_any", 'label' => "Supprimer (tous) : {$label['plural']}"],
                ['key' => "{$permissionPrefix}.publish", 'label' => "Publier (les siens) : {$label['singular']}"],
                ['key' => "{$permissionPrefix}.publish_any", 'label' => "Publier (tous) : {$label['plural']}"],
            ],
            'menus' => [
                'admin' => [
                    [
                        'label' => $label['plural'],
                        'route' => 'admin.content.index',
                        'route_params' => ['contentType' => $dirSlug],
                        'permission' => "{$permissionPrefix}.view",
                    ],
                ],
            ],
        ];

        return (string) json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function fieldColumns(ContentType $contentType): string
    {
        return collect((array) ($contentType->blueprint['fields'] ?? []))
            ->map(function (array $field): string {
                $fieldType = $this->fields->resolve($field['type']);

                return $fieldType->columnDefinition($field['key'], $field['options'] ?? []);
            })
            ->filter(fn (string $column): bool => $column !== '')
            ->map(fn (string $column): string => '            '.$column)
            ->implode("\n");
    }

    private function castsList(ContentType $contentType): string
    {
        return collect((array) ($contentType->blueprint['fields'] ?? []))
            ->map(function (array $field): ?string {
                $fieldType = $this->fields->resolve($field['type']);
                $cast = $fieldType->cast($field['options'] ?? []);

                return $cast === null ? null : "            '{$field['key']}' => '{$cast}',";
            })
            ->filter()
            ->implode("\n");
    }

    private function fillableList(ContentType $contentType): string
    {
        $columns = ['status', 'published_at', 'author_id'];

        if ($contentType->unpublishAtEnabled()) {
            $columns[] = 'unpublish_at';
        }

        if ($contentType->is_addressable) {
            $columns[] = 'slug';
        }

        foreach ($contentType->blueprint['fields'] ?? [] as $field) {
            $fieldType = $this->fields->resolve($field['type']);

            if ($fieldType->columnDefinition($field['key'], $field['options'] ?? []) === '') {
                continue;
            }

            $columns[] = $field['key'];
        }

        foreach ($this->ownColumnRelations($contentType) as $relation) {
            if ($relation['type'] === 'polymorphic') {
                $columns[] = "{$relation['key']}_type";
            }

            $columns[] = "{$relation['key']}_id";
        }

        return collect($columns)
            ->map(fn (string $column): string => "        '{$column}',")
            ->implode("\n");
    }

    private function relationColumns(ContentType $contentType): string
    {
        return collect($this->ownColumnRelations($contentType))
            ->map(fn (array $relation): string => $this->relationDefinitions->columnsDefinition(
                $relation,
                $this->relationTargets->resolve((string) $relation['target']),
            ))
            ->implode("\n");
    }

    private function relationMethods(ContentType $contentType): string
    {
        return collect((array) ($contentType->blueprint['relations'] ?? []))
            ->map(fn (array $relation): string => $this->relationDefinitions->eloquentMethod(
                $relation,
                $this->relationTargets->resolve((string) $relation['target']),
                $contentType,
            ))
            ->implode("\n\n");
    }

    /**
     * @return list<array{filename: string, contents: string}>
     */
    private function pivotMigrations(ContentType $contentType): array
    {
        return array_values(array_filter(collect((array) ($contentType->blueprint['relations'] ?? []))
            ->map(fn (array $relation) => $this->relationDefinitions->pivotMigration(
                $relation,
                $contentType,
                $this->relationTargets->resolve((string) $relation['target']),
            ))
            ->all()));
    }

    /**
     * Relations qui ajoutent une colonne côté déclarant (tout sauf many_to_many).
     *
     * @return list<array<string, mixed>>
     */
    private function ownColumnRelations(ContentType $contentType): array
    {
        return array_values(array_filter(
            (array) ($contentType->blueprint['relations'] ?? []),
            fn (array $relation): bool => $relation['type'] !== 'many_to_many',
        ));
    }
}
