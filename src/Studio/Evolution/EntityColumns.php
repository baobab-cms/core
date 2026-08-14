<?php

declare(strict_types=1);

namespace Baobab\Studio\Evolution;

use Baobab\ContentTypes\Fields\FieldRegistry;
use Baobab\ContentTypes\Generator\ColumnNullability;
use Baobab\Studio\Generator\StudioRelationDefinitionGenerator;
use Baobab\Studio\Relations\StudioRelationTargetResolver;

/**
 * Colonnes qu'une entité de blueprint **vise** en base, sous une forme
 * exploitable par un diff : nom → ligne `$table->...` et nullabilité.
 *
 * `ModuleGenerator` sait déjà les produire, mais sous la seule forme dont il a
 * besoin — un bloc de texte à insérer dans un stub `CREATE TABLE`. Un bloc de
 * texte ne se compare pas au schéma d'une base. Cette classe rend la même
 * information, colonne par colonne, à partir des mêmes sources (`FieldType`,
 * `StudioRelationDefinitionGenerator`) : les deux vues ne peuvent donc pas
 * diverger sur ce qu'une ligne contient, seulement sur la façon de la présenter.
 *
 * La nullabilité se lit **dans la ligne rendue**, jamais dans le drapeau
 * `required` du champ : les deux coïncident depuis le n° 137, sauf pour les
 * abstentions de `ColumnNullability` (clés étrangères vers `media`), et c'est la
 * ligne qui dit la vérité sur ce que la migration écrira.
 *
 * @phpstan-type TargetColumn array{definition: string, nullable: bool}
 */
final class EntityColumns
{
    public function __construct(
        private readonly FieldRegistry $fields,
        private readonly StudioRelationTargetResolver $relationTargets,
        private readonly StudioRelationDefinitionGenerator $relationDefinitions,
    ) {}

    /**
     * @param  array<string, mixed>  $entity
     * @param  list<array{key: string, table: string}>  $siblings
     * @return array<string, TargetColumn>
     */
    public function target(array $entity, array $siblings): array
    {
        return [...$this->fromFields($entity), ...$this->fromRelations($entity, $siblings)];
    }

    /**
     * Noms de colonnes seuls — ce qu'il faut pour délimiter la propriété du
     * générateur sans avoir à rendre les lignes.
     *
     * @param  array<string, mixed>  $entity
     * @param  list<array{key: string, table: string}>  $siblings
     * @return list<string>
     */
    public function names(array $entity, array $siblings): array
    {
        return array_keys($this->target($entity, $siblings));
    }

    /**
     * @param  array<string, mixed>  $entity
     * @return array<string, TargetColumn>
     */
    private function fromFields(array $entity): array
    {
        $columns = [];

        foreach ((array) ($entity['fields'] ?? []) as $field) {
            $definition = ColumnNullability::apply(
                $this->fields->resolve((string) $field['type'])->columnDefinition((string) $field['key'], $field['options'] ?? []),
                (bool) ($field['required'] ?? false),
            );

            // Un champ sans colonne propre (`gallery`, matérialisé dans
            // media_usages) ne participe pas au schéma de la table.
            if ($definition === '') {
                continue;
            }

            $columns[(string) $field['key']] = [
                'definition' => $definition,
                'nullable' => self::isNullable($definition),
            ];
        }

        return $columns;
    }

    /**
     * Une relation `many_to_many` n'apparaît pas ici : elle ne pose aucune
     * colonne sur la table déclarante, seulement une table pivot — donc une
     * table à créer, pas une colonne à ajouter.
     *
     * @param  array<string, mixed>  $entity
     * @param  list<array{key: string, table: string}>  $siblings
     * @return array<string, TargetColumn>
     */
    private function fromRelations(array $entity, array $siblings): array
    {
        $columns = [];

        foreach ((array) ($entity['relations'] ?? []) as $relation) {
            if ($relation['type'] === 'many_to_many') {
                continue;
            }

            $key = (string) $relation['key'];
            $definition = trim($this->relationDefinitions->columnsDefinition(
                $relation,
                $this->relationTargets->resolve((string) $relation['target'], $siblings),
            ));

            if ($definition === '') {
                continue;
            }

            // `nullableMorphs()` est la seule ligne qui pose deux colonnes d'un
            // coup. Elles vont et viennent ensemble : la ligne entière est donc
            // portée par la colonne `_id`, et `_type` ne sert qu'à constater la
            // présence de la paire — l'ajouter deux fois écrirait la relation en
            // double dans la migration.
            if (str_contains($definition, '->nullableMorphs(')) {
                $columns["{$key}_type"] = ['definition' => '', 'nullable' => true];
                $columns["{$key}_id"] = ['definition' => $definition, 'nullable' => true];

                continue;
            }

            $columns["{$key}_id"] = [
                'definition' => $definition,
                'nullable' => self::isNullable($definition),
            ];
        }

        return $columns;
    }

    private static function isNullable(string $definition): bool
    {
        return str_contains($definition, '->nullable()') || str_contains($definition, '->nullableMorphs(');
    }
}
