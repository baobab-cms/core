<?php

declare(strict_types=1);

namespace Baobab\ContentTypes\Generator;

use Baobab\ContentTypes\Generator\Exceptions\CircularTableDependencyException;

/**
 * L'ordre dans lequel les tables d'un module doivent être créées, dérivé du
 * **graphe de dépendances** et non d'une horloge (suivi n° 120).
 *
 * **Pourquoi l'ordre et l'identité sont le même problème.** Laravel identifie
 * une migration par son nom de fichier et les exécute dans l'ordre alphabétique
 * de ces noms. Tant que le nom porte un horodatage et un tirage aléatoire, il
 * décide *à la fois* de l'ordre d'exécution et de l'identité — et il les décide
 * mal dans les deux cas : deux migrations générées dans la même seconde ne sont
 * départagées que par leur suffixe aléatoire (une clé étrangère a ainsi été
 * jouée avant la table qu'elle référence, `SQLSTATE[HY000] 1824`, défaut
 * intermittent), et une archive régénérée sur une autre machine forge des noms
 * neufs que la base cible ne reconnaît pas, si bien qu'elle rejoue un
 * `CREATE TABLE` sur une table existante.
 *
 * Un rang issu du graphe règle les deux : il est **stable** (même blueprint,
 * même nom de fichier, quelle que soit la machine ou l'heure) et il est
 * **correct** (une table référencée précède celle qui la référence).
 *
 * Le préfixe synthétique retenu par `MigrationFilename` suit le précédent de
 * Laravel lui-même, dont le squelette livre `0001_01_01_000000_create_users_
 * table.php` : la date d'un nom de migration n'a jamais eu d'autre rôle que de
 * porter un ordre.
 *
 * **Ce qui n'est pas une dépendance** : une entité qui se référence elle-même
 * (hiérarchie parent/enfant) — la colonne naît dans le même `CREATE TABLE` que
 * la clé primaire qu'elle vise —, et une cible **hors du module** (Content Type
 * existant, modèle du Core), dont la table est déjà là. Seules les tables que
 * ce module crée participent au tri.
 */
final class MigrationOrder
{
    /**
     * @param  array<string, list<string>>  $dependencies  table => tables qu'elle référence
     * @return list<string> tables, dans l'ordre de création
     *
     * @throws CircularTableDependencyException
     */
    public static function sort(array $dependencies): array
    {
        $sorted = [];
        $state = [];

        foreach (array_keys($dependencies) as $table) {
            self::visit((string) $table, $dependencies, $state, $sorted, []);
        }

        return $sorted;
    }

    /**
     * Parcours en profondeur, avec la pile courante pour nommer le cycle plutôt
     * que d'annoncer sa seule existence — un message qui dit « a → b → a » se
     * corrige, un message qui dit « cycle détecté » se subit.
     *
     * @param  array<string, list<string>>  $dependencies
     * @param  array<string, string>  $state
     * @param  list<string>  $sorted
     * @param  list<string>  $stack
     */
    private static function visit(
        string $table,
        array $dependencies,
        array &$state,
        array &$sorted,
        array $stack,
    ): void {
        if (($state[$table] ?? null) === 'done') {
            return;
        }

        if (($state[$table] ?? null) === 'visiting') {
            $cycle = array_slice($stack, (int) array_search($table, $stack, true));

            throw CircularTableDependencyException::forCycle([...$cycle, $table]);
        }

        $state[$table] = 'visiting';
        $stack[] = $table;

        foreach ($dependencies[$table] ?? [] as $dependency) {
            // Une table hors du module n'a pas d'entrée dans le graphe : elle
            // existe déjà, il n'y a rien à ordonner avant elle.
            if ($dependency !== $table && array_key_exists($dependency, $dependencies)) {
                self::visit($dependency, $dependencies, $state, $sorted, $stack);
            }
        }

        $state[$table] = 'done';
        $sorted[] = $table;
    }
}
