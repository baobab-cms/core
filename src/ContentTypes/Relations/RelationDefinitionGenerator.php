<?php

declare(strict_types=1);

namespace Baobab\ContentTypes\Relations;

use Baobab\ContentTypes\Generator\MigrationFilename;
use Baobab\ContentTypes\Models\ContentType;
use Illuminate\Support\Str;

/**
 * Génère la structure (colonnes / table pivot) et la méthode Eloquent d'une
 * relation validée, côté déclarant uniquement (spec 02 §5, voir le plan de
 * M3 point 3 pour la décision de ne pas générer l'inverse automatiquement).
 *
 * @phpstan-type ResolvedTarget array{class: string, table: string, key: string}
 */
final class RelationDefinitionGenerator
{
    /**
     * @param  array<string, mixed>  $relation
     * @param  ResolvedTarget  $target
     */
    public function columnsDefinition(array $relation, array $target): string
    {
        $type = RelationType::from((string) $relation['type']);
        $key = (string) $relation['key'];
        $onDelete = $this->onDeleteMethod($relation);

        return match ($type) {
            RelationType::OneToOne => "            \$table->foreignId('{$key}_id')->nullable()->unique()->constrained('{$target['table']}')->{$onDelete}();",
            RelationType::OneToMany => "            \$table->foreignId('{$key}_id')->nullable()->constrained('{$target['table']}')->{$onDelete}();",
            RelationType::Polymorphic => "            \$table->nullableMorphs('{$key}');",
            RelationType::ManyToMany => '',
        };
    }

    /**
     * @param  array<string, mixed>  $relation
     * @param  ResolvedTarget  $target
     */
    public function eloquentMethod(array $relation, array $target, ContentType $owner): string
    {
        $type = RelationType::from((string) $relation['type']);
        $key = (string) $relation['key'];
        $method = Str::camel($key);
        $targetClass = '\\'.ltrim($target['class'], '\\');

        return match ($type) {
            RelationType::OneToOne => $this->methodLines(
                $method,
                '\Illuminate\Database\Eloquent\Relations\BelongsTo',
                "return \$this->belongsTo({$targetClass}::class, '{$key}_id');"
            ),
            RelationType::OneToMany => $this->methodLines(
                $method,
                '\Illuminate\Database\Eloquent\Relations\BelongsTo',
                "return \$this->belongsTo({$targetClass}::class, '{$key}_id');"
            ),
            RelationType::ManyToMany => $this->methodLines(
                $method,
                '\Illuminate\Database\Eloquent\Relations\BelongsToMany',
                "return \$this->belongsToMany({$targetClass}::class, '{$this->pivotTableName($owner, $target)}');"
            ),
            RelationType::Polymorphic => $this->methodLines(
                $method,
                '\Illuminate\Database\Eloquent\Relations\MorphTo',
                'return $this->morphTo();'
            ),
        };
    }

    /**
     * Champ GraphQL de la relation (M7 point 3, spec 08 §3.2) — même méthode
     * Eloquent que `eloquentMethod()` (nom de champ = nom de méthode camelCase,
     * la résolution par défaut des directives Lighthouse), donc aucune
     * directive n'a besoin d'un argument `relation:` explicite. `OneToOne` et
     * `OneToMany` génèrent tous deux un `belongsTo()` côté déclarant (FK sur
     * le type propriétaire, jamais l'inverse — voir docblock de classe) :
     * même directive `@belongsTo`, même cardinalité simple côté GraphQL.
     *
     * @param  array<string, mixed>  $relation
     * @param  ResolvedTarget  $target
     */
    public function graphqlField(array $relation, array $target): string
    {
        $type = RelationType::from((string) $relation['type']);
        $field = Str::camel((string) $relation['key']);
        $targetType = Str::studly((string) $target['key']);

        return match ($type) {
            RelationType::OneToOne, RelationType::OneToMany => "  {$field}: {$targetType} @belongsTo",
            RelationType::ManyToMany => "  {$field}: [{$targetType}!]! @belongsToMany",
            RelationType::Polymorphic => "  {$field}: {$targetType} @morphTo",
        };
    }

    /**
     * @param  array<string, mixed>  $relation
     * @param  ResolvedTarget  $target
     * @return array{filename: string, contents: string}|null
     */
    public function pivotMigration(array $relation, ContentType $owner, array $target): ?array
    {
        if (RelationType::from((string) $relation['type']) !== RelationType::ManyToMany) {
            return null;
        }

        $pivotTable = $this->pivotTableName($owner, $target);
        $ownerColumn = Str::snake($owner->key).'_id';
        $targetColumn = Str::snake($target['key']).'_id';

        $contents = <<<PHP
        <?php

        declare(strict_types=1);

        use Illuminate\Database\Migrations\Migration;
        use Illuminate\Database\Schema\Blueprint;
        use Illuminate\Support\Facades\Schema;

        return new class extends Migration
        {
            public function up(): void
            {
                Schema::create('{$pivotTable}', function (Blueprint \$table): void {
                    \$table->foreignId('{$ownerColumn}')->constrained('{$owner->table_name}')->cascadeOnDelete();
                    \$table->foreignId('{$targetColumn}')->constrained('{$target['table']}')->cascadeOnDelete();
                    \$table->primary(['{$ownerColumn}', '{$targetColumn}']);
                });
            }

            public function down(): void
            {
                Schema::dropIfExists('{$pivotTable}');
            }
        };

        PHP;

        return [
            // La table pivot est écrite dans le module du déclarant : c'est
            // donc son dossier qui décide du nom réutilisable.
            'filename' => MigrationFilename::create($owner->moduleDir(), $pivotTable),
            'contents' => $contents,
        ];
    }

    /**
     * @param  ResolvedTarget  $target
     */
    private function pivotTableName(ContentType $owner, array $target): string
    {
        $parts = [Str::snake($owner->key), Str::snake((string) $target['key'])];
        sort($parts);

        return 'ct_'.implode('_', $parts);
    }

    /**
     * @param  array<string, mixed>  $relation
     */
    private function onDeleteMethod(array $relation): string
    {
        return match ($relation['on_delete'] ?? 'restrict') {
            'cascade' => 'cascadeOnDelete',
            'set_null' => 'nullOnDelete',
            default => 'restrictOnDelete',
        };
    }

    private function methodLines(string $method, string $returnType, string $body): string
    {
        return implode("\n", [
            "            public function {$method}(): {$returnType}",
            '            {',
            "                {$body}",
            '            }',
        ]);
    }
}
