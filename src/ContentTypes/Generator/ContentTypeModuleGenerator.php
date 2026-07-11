<?php

declare(strict_types=1);

namespace Baobab\ContentTypes\Generator;

use Baobab\ContentTypes\Fields\FieldRegistry;
use Baobab\ContentTypes\Models\ContentType;
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
        $permissionPrefix = 'content_types.'.Str::snake($key);
        $moduleDir = rtrim((string) config('baobab.content_types.modules_path'), '/')."/content-{$dirSlug}";

        $this->checksums->write($moduleDir, 'module.json', $this->moduleJson(
            $contentType,
            $moduleName,
            $namespace,
            $permissionPrefix,
        ));

        $this->checksums->write(
            $moduleDir,
            'database/migrations/'.$this->migrationTimestamp()."_create_{$contentType->table_name}_table.php",
            $this->renderer->render(StubRenderer::stubPath('migration'), [
                'table_name' => $contentType->table_name,
                'slug_column' => $contentType->is_addressable
                    ? "            \$table->string('slug')->unique();\n"
                    : '',
                'field_columns' => $this->fieldColumns($contentType),
            ]),
        );

        $this->checksums->write($moduleDir, "src/Models/{$key}.php", $this->renderer->render(StubRenderer::stubPath('model'), [
            'namespace' => $namespace,
            'key' => $key,
            'table_name' => $contentType->table_name,
            'fillable' => $this->fillableList($contentType),
            'casts' => $this->castsList($contentType),
        ]));

        $this->checksums->write($moduleDir, "src/Policies/{$key}Policy.php", $this->renderer->render(StubRenderer::stubPath('policy'), [
            'namespace' => $namespace,
            'key' => $key,
            'permission_prefix' => $permissionPrefix,
        ]));

        $this->checksums->write($moduleDir, "src/Providers/{$key}ServiceProvider.php", $this->renderer->render(StubRenderer::stubPath('provider'), [
            'namespace' => $namespace,
            'key' => $key,
        ]));

        return $moduleName;
    }

    private function moduleJson(ContentType $contentType, string $moduleName, string $namespace, string $permissionPrefix): string
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
                ['key' => "{$permissionPrefix}.update", 'label' => "Modifier : {$label['singular']}"],
                ['key' => "{$permissionPrefix}.delete", 'label' => "Supprimer : {$label['singular']}"],
            ],
        ];

        return (string) json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * `Y_m_d_His` seul peut entrer en collision entre deux Content Types
     * générés dans la même seconde (tests, imports en rafale...) — Laravel
     * traiterait alors la seconde migration comme « déjà exécutée ». Un
     * suffixe aléatoire élimine la collision sans changer la convention de
     * tri chronologique du nom de fichier.
     */
    private function migrationTimestamp(): string
    {
        return now()->format('Y_m_d_His').'_'.substr(bin2hex(random_bytes(3)), 0, 6);
    }

    private function fieldColumns(ContentType $contentType): string
    {
        return collect((array) ($contentType->blueprint['fields'] ?? []))
            ->map(function (array $field): string {
                $fieldType = $this->fields->resolve($field['type']);

                return '            '.$fieldType->columnDefinition($field['key'], $field['options'] ?? []);
            })
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

        if ($contentType->is_addressable) {
            $columns[] = 'slug';
        }

        foreach ($contentType->blueprint['fields'] ?? [] as $field) {
            $columns[] = $field['key'];
        }

        return collect($columns)
            ->map(fn (string $column): string => "        '{$column}',")
            ->implode("\n");
    }
}
