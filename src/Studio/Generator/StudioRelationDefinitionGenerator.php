<?php

declare(strict_types=1);

namespace Baobab\Studio\Generator;

use Baobab\ContentTypes\Generator\MigrationTimestamp;
use Baobab\ContentTypes\Relations\RelationType;
use Illuminate\Support\Str;

/**
 * Génère la structure (colonnes / table pivot) et la méthode Eloquent d'une
 * relation de blueprint Wizard Studio (spec-modules §5.2 étape 2), côté
 * déclarant uniquement — même règle que
 * `Baobab\ContentTypes\Relations\RelationDefinitionGenerator`, dont cette
 * classe est un pendant délibérément découplé : les entités du Studio ne
 * sont **pas** des Content Types (pas de table `ct_*`, spec 02 §1.2 ne
 * s'applique qu'aux Content Types réels), donc le nom de table pivot ne
 * porte aucun préfixe `ct_`.
 *
 * @phpstan-type ResolvedTarget array{class: string|null, table: string|null, key: string}
 */
final class StudioRelationDefinitionGenerator
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
    public function eloquentMethod(array $relation, array $target, string $ownerNamespace, string $ownerKey): string
    {
        $type = RelationType::from((string) $relation['type']);
        $key = (string) $relation['key'];
        $method = Str::camel($key);
        $targetClass = '\\'.ltrim($target['class'] ?? "{$ownerNamespace}\\Models\\{$target['key']}", '\\');

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
                "return \$this->belongsToMany({$targetClass}::class, '{$this->pivotTableName($ownerKey, $target['key'])}');"
            ),
            RelationType::Polymorphic => $this->methodLines(
                $method,
                '\Illuminate\Database\Eloquent\Relations\MorphTo',
                'return $this->morphTo();'
            ),
        };
    }

    /**
     * @param  array<string, mixed>  $relation
     * @param  ResolvedTarget  $target
     * @return array{filename: string, contents: string}|null
     */
    public function pivotMigration(array $relation, string $ownerKey, string $ownerTable, array $target): ?array
    {
        if (RelationType::from((string) $relation['type']) !== RelationType::ManyToMany) {
            return null;
        }

        $pivotTable = $this->pivotTableName($ownerKey, $target['key']);
        $ownerColumn = Str::snake($ownerKey).'_id';
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
                    \$table->foreignId('{$ownerColumn}')->constrained('{$ownerTable}')->cascadeOnDelete();
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
            'filename' => 'database/migrations/'.MigrationTimestamp::generate()."_create_{$pivotTable}_table.php",
            'contents' => $contents,
        ];
    }

    private function pivotTableName(string $ownerKey, string $targetKey): string
    {
        $parts = [Str::snake($ownerKey), Str::snake($targetKey)];
        sort($parts);

        return implode('_', $parts);
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
