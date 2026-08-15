<?php

declare(strict_types=1);

namespace Baobab\Studio\Generator;

use Baobab\ContentTypes\Generator\MigrationFilename;
use Baobab\ContentTypes\Relations\RelationType;
use Illuminate\Support\Str;

/**
 * Génère la structure (colonnes / table pivot), la méthode Eloquent et le
 * champ GraphQL d'une relation déclarée à un blueprint, côté déclarant
 * uniquement (spec-modules §5.2 étape 2, spec 02 §5 — voir le plan de M3
 * point 3 pour la décision de ne pas générer l'inverse automatiquement).
 *
 * **Générateur unique depuis la Pass A du M8 point 2** (suivi n° 157) : il
 * absorbe `Baobab\ContentTypes\Relations\RelationDefinitionGenerator`, dont il
 * était le pendant à une différence près — le préfixe des tables pivots, `ct_`
 * pour un Content Type (spec 02 §9 décision 1) et vide pour une entité de
 * module. Cette différence est devenue un réglage (`withPivotPrefix()`) porté
 * par le profil de génération, plutôt qu'une seconde classe.
 *
 * @phpstan-type ResolvedTarget array{class: string|null, table: string|null, key: string}
 */
final class StudioRelationDefinitionGenerator
{
    private string $pivotPrefix = '';

    /**
     * Copie réglée sur un préfixe de table pivot — l'instance reste
     * immuable, et le conteneur continue de n'en résoudre qu'une.
     */
    public function withPivotPrefix(string $prefix): self
    {
        $clone = clone $this;
        $clone->pivotPrefix = $prefix;

        return $clone;
    }

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
    public function pivotMigration(array $relation, string $ownerKey, string $ownerTable, array $target, string $moduleDir, ?int $rank = null): ?array
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
            'filename' => MigrationFilename::create($moduleDir, $pivotTable, $rank),
            'contents' => $contents,
        ];
    }

    /**
     * Publique depuis le n° 120 : le générateur doit connaître le nom du pivot
     * pour le placer dans le graphe de dépendances, avant même de produire sa
     * migration. Le nom reste dérivé des deux clés triées — deux entités liées
     * dans un sens ou dans l'autre donnent le même pivot.
     */
    public function pivotTableName(string $ownerKey, string $targetKey): string
    {
        $parts = [Str::snake($ownerKey), Str::snake($targetKey)];
        sort($parts);

        return $this->pivotPrefix.implode('_', $parts);
    }

    /**
     * Champ GraphQL de la relation (spec 08 §3.2) — même méthode Eloquent que
     * `eloquentMethod()` (nom de champ = nom de méthode camelCase, la
     * résolution par défaut des directives Lighthouse), donc aucune directive
     * n'a besoin d'un argument `relation:` explicite. `OneToOne` et `OneToMany`
     * génèrent tous deux un `belongsTo()` côté déclarant (FK sur le type
     * propriétaire, jamais l'inverse — voir le docblock de classe) : même
     * directive `@belongsTo`, même cardinalité simple côté GraphQL.
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
