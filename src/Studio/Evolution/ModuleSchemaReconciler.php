<?php

declare(strict_types=1);

namespace Baobab\Studio\Evolution;

use Baobab\Studio\Blueprint\ModuleBlueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Écart entre le schéma que le blueprint **vise** et celui que la base porte
 * **réellement** (suivi n° 111, qui porte le n° 137).
 *
 * # Pourquoi la base et non l'ancien blueprint
 *
 * Le chemin Content Type compare deux blueprints (`BlueprintDiffer`), et c'est
 * cohérent là-bas : `ContentType->blueprint` conserve l'état construit. Côté
 * Studio, `module_blueprints.blueprint` porte le brouillon **en cours de
 * saisie**, réécrit à chaque étape du wizard — il ne dit rien de ce qui est en
 * base. Surtout, un diff blueprint↔blueprint est structurellement aveugle au
 * n° 137 : quand une colonne n'a jamais correspondu à son champ, les deux
 * blueprints sont identiques et le diff est vide, alors que la base est fausse.
 * Le seul endroit qui sait ce qui est en base, c'est la base.
 *
 * # Ce que l'instantané fait ici
 *
 * `module_blueprints.generated_blueprint` ne sert **pas** de terme de
 * comparaison, mais de **frontière de propriété** : il énumère les colonnes que
 * le générateur a écrites, donc les seules qu'il peut légitimement supprimer.
 * Une colonne ajoutée à la main par un développeur dans sa propre migration n'y
 * figure pas et n'est jamais proposée à la suppression (spec 01 §5.4 :
 * « jamais d'écrasement silencieux »).
 *
 * Conséquence assumée pour un module généré avant cette passe, qui n'a pas
 * d'instantané : la dégradation est **sûre**, pas cassée. Il gagne ses colonnes
 * manquantes et ses nullabilités corrigées ; rien n'y est jamais supprimé.
 *
 * # Ce qui n'est pas de son ressort
 *
 * Une table absente de la base — entité ajoutée après l'installation, table
 * pivot d'un `many_to_many` nouveau — ne produit aucun plan : sa migration de
 * création est déjà écrite sur disque par `ModuleGenerator` et n'attend que
 * d'être jouée. Depuis le n° 120, son nom est dérivé du rang dans le graphe, il
 * est donc absent de la table `migrations` et un simple `migrate` le rattrape.
 * Le réconciliateur la signale (`pending`) pour que l'appelant puisse le dire,
 * et ne génère rien.
 *
 * Les renommages ne s'y devinent pas non plus : ils se déclarent, par
 * `renamed_from` sur le champ, exactement comme dans `BlueprintDiffer`. Comparé
 * à la base, un renommage est indiscernable d'une suppression suivie d'un ajout,
 * et l'ambiguïté n'est pas résoluble sans intention explicite.
 *
 * @phpstan-type ColumnChange array{name: string, definition: string, nullable: bool}
 * @phpstan-type TablePlan array{
 *     table: string,
 *     added: list<ColumnChange>,
 *     dropped: list<array{name: string, definition: string}>,
 *     renamed: list<array{from: string, to: string}>,
 *     retightened: list<array{name: string, definition: string, nullable: bool, nulls: int}>,
 * }
 */
final class ModuleSchemaReconciler
{
    public function __construct(
        private readonly EntityColumns $columns,
    ) {}

    /**
     * @param  array<string, mixed>|null  $snapshot  Blueprint tel que généré la dernière fois, ou null.
     * @return array{tables: list<TablePlan>, pending: list<string>}
     */
    public function reconcile(ModuleBlueprint $target, ?array $snapshot = null): array
    {
        $entities = $target->entities();

        /** @var list<array{key: string, table: string}> $siblings */
        $siblings = array_map(
            static fn (array $entity): array => ['key' => $entity['key'], 'table' => $entity['table']],
            $entities,
        );

        $plans = [];
        $pending = [];

        foreach ($entities as $entity) {
            $table = (string) $entity['table'];

            if (! Schema::hasTable($table)) {
                $pending[] = $table;

                continue;
            }

            $plan = $this->planFor($entity, $table, $siblings, $snapshot);

            if ($this->isEmpty($plan)) {
                continue;
            }

            $plans[] = $plan;
        }

        return ['tables' => $plans, 'pending' => $pending];
    }

    /**
     * @param  array<string, mixed>  $entity
     * @param  list<array{key: string, table: string}>  $siblings
     * @param  array<string, mixed>|null  $snapshot
     * @return TablePlan
     */
    private function planFor(array $entity, string $table, array $siblings, ?array $snapshot): array
    {
        $intended = $this->columns->target($entity, $siblings);
        $actual = $this->actualColumns($table);
        $owned = $this->ownedColumns($entity, $siblings, $snapshot);

        $renamed = $this->renames($entity, $table, $actual, $intended);

        // Une colonne renommée est déjà traitée : ni son ancien nom (encore en
        // base) ni son nouveau (pas encore) ne doivent repasser par l'ajout ou
        // la suppression.
        foreach ($renamed as $rename) {
            unset($actual[$rename['from']], $intended[$rename['to']]);
        }

        $added = [];
        $retightened = [];

        foreach ($intended as $name => $column) {
            if (! array_key_exists($name, $actual)) {
                // `nullableMorphs` pose ses deux colonnes en une ligne, portée
                // par la seule colonne `_id` (cf. EntityColumns) : la moitié
                // `_type` n'a rien à écrire pour elle-même.
                if ($column['definition'] !== '') {
                    $added[] = ['name' => $name, ...$column];
                }

                continue;
            }

            if ($column['definition'] === '' || $actual[$name] === $column['nullable']) {
                continue;
            }

            $retightened[] = [
                'name' => $name,
                'definition' => $column['definition'],
                'nullable' => $column['nullable'],
                'nulls' => $column['nullable'] ? 0 : $this->countNulls($table, $name),
            ];
        }

        $dropped = [];

        foreach ($owned as $name => $definition) {
            if (array_key_exists($name, $actual) && ! array_key_exists($name, $intended)) {
                $dropped[] = ['name' => $name, 'definition' => $definition];
            }
        }

        return [
            'table' => $table,
            'added' => $added,
            'dropped' => $dropped,
            'renamed' => $renamed,
            'retightened' => $retightened,
        ];
    }

    /**
     * Colonnes que le générateur a écrites pour cette entité et qui ne sont plus
     * visées — donc les seules supprimables. Elles se lisent dans l'instantané ;
     * sans instantané, l'ensemble est vide et rien n'est jamais supprimé.
     *
     * @param  array<string, mixed>  $entity
     * @param  list<array{key: string, table: string}>  $siblings
     * @param  array<string, mixed>|null  $snapshot
     * @return array<string, string> nom → ligne de colonne d'origine, pour le down()
     */
    private function ownedColumns(array $entity, array $siblings, ?array $snapshot): array
    {
        if ($snapshot === null) {
            return [];
        }

        $previous = null;

        foreach ((array) ($snapshot['entities'] ?? []) as $candidate) {
            if (($candidate['key'] ?? null) === ($entity['key'] ?? null)) {
                $previous = $candidate;

                break;
            }
        }

        if ($previous === null) {
            return [];
        }

        $owned = [];

        foreach ($this->columns->target($previous, $siblings) as $name => $column) {
            $owned[$name] = $column['definition'];
        }

        return $owned;
    }

    /**
     * Renommages **déclarés** dont la base porte encore l'ancien nom. Un
     * renommage déjà appliqué (ancien nom absent) n'a plus rien à faire.
     *
     * @param  array<string, mixed>  $entity
     * @param  array<string, bool>  $actual
     * @param  array<string, array{definition: string, nullable: bool}>  $intended
     * @return list<array{from: string, to: string}>
     */
    private function renames(array $entity, string $table, array $actual, array $intended): array
    {
        $renames = [];

        foreach ((array) ($entity['fields'] ?? []) as $field) {
            if (! isset($field['renamed_from'])) {
                continue;
            }

            $from = (string) $field['renamed_from'];
            $to = (string) $field['key'];

            if (! array_key_exists($from, $actual) || array_key_exists($to, $actual) || ! array_key_exists($to, $intended)) {
                continue;
            }

            $renames[] = ['from' => $from, 'to' => $to];
        }

        return $renames;
    }

    /**
     * @return array<string, bool> nom → nullable
     */
    private function actualColumns(string $table): array
    {
        $columns = [];

        foreach (Schema::getColumns($table) as $column) {
            $columns[(string) $column['name']] = (bool) $column['nullable'];
        }

        return $columns;
    }

    private function countNulls(string $table, string $column): int
    {
        return DB::table($table)->whereNull($column)->count();
    }

    /**
     * @param  TablePlan  $plan
     */
    private function isEmpty(array $plan): bool
    {
        return $plan['added'] === []
            && $plan['dropped'] === []
            && $plan['renamed'] === []
            && $plan['retightened'] === [];
    }
}
