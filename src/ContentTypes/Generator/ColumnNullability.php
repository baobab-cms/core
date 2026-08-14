<?php

declare(strict_types=1);

namespace Baobab\ContentTypes\Generator;

/**
 * Applique le drapeau `required` du blueprint à une ligne `$table->...(...)`
 * produite par `FieldType::columnDefinition()` (suivi n° 137).
 *
 * La nullabilité est une propriété du **champ**, pas du **type** : deux champs
 * `text`, l'un obligatoire et l'autre facultatif, partagent le même type et
 * doivent pourtant produire deux colonnes différentes. Elle se décide donc ici,
 * là où le générateur assemble la ligne et où le drapeau est disponible, et non
 * dans chaque `columnDefinition()` — qui ne reçoit que les `options` du champ et
 * n'a aucun moyen de la connaître. C'est la raison pour laquelle le défaut
 * existait : chaque type écrivait en dur une nullabilité qu'il ne pouvait pas
 * décider.
 *
 * Le catalogue se trompait **dans les deux sens**, et les deux ont la même
 * cause :
 *
 * - six types écrivaient une colonne NOT NULL quel que soit `required` —
 *   `text`, `textarea`, `richtext`, `slug`, `decimal`, `integer`. Un champ
 *   facultatif omis provoquait une `QueryException`, donc une erreur serveur,
 *   là où la validation générée disait `nullable` ;
 * - six autres écrivaient une colonne nullable quel que soit `required` —
 *   `date`, `datetime`, `json`, `select`, `radio`, `multiselect` —, laissant la
 *   base accepter NULL là où le blueprint exige une valeur.
 *
 * `boolean` n'appartient à aucun des deux groupes : sa colonne est NOT NULL mais
 * porte un `->default(false)`, donc une valeur omise ne casse rien. Ce défaut de
 * valeur *est* la réponse à `required: false`, et le lui retirer changerait la
 * sémantique du type (une case non cochée vaut faux, pas « inconnu ») — la ligne
 * traverse donc cette classe sans être touchée, le `->default(false)` la rendant
 * insensible à l'ajout ou au retrait de `->nullable()`.
 */
final class ColumnNullability
{
    /**
     * @param  string  $columnDefinition  Ligne rendue par `FieldType::columnDefinition()`, éventuellement vide.
     */
    public static function apply(string $columnDefinition, bool $required): string
    {
        // Un champ sans colonne propre (`gallery`, matérialisé dans
        // media_usages) n'a pas de nullabilité à porter.
        if ($columnDefinition === '' || self::isForeignKey($columnDefinition)) {
            return $columnDefinition;
        }

        $definition = str_replace('->nullable()', '', $columnDefinition);

        return $required ? $definition : rtrim($definition, ';').'->nullable();';
    }

    /**
     * Une référence à un média (`file`, `image`) garde la nullabilité écrite par
     * son type, quel que soit `required` — abstention délibérée, pour deux
     * raisons qui pointent dans le même sens.
     *
     * Sur le fond : sa colonne porte `->constrained('media')->nullOnDelete()`, et
     * une contrainte qui écrit NULL à la suppression du média exige une colonne
     * qui l'accepte. La rendre NOT NULL ferait échouer la suppression d'un média
     * au lieu de détacher l'entrée qui le référence. L'obligation d'un tel champ
     * reste portée par la validation, seule couche où elle ne détruit rien.
     *
     * Sur la forme : ces lignes sont aussi les seules où la position du modifieur
     * compte. `constrained()` retourne une `ForeignKeyDefinition` et non la
     * `ColumnDefinition` — un `->nullable()` ajouté en fin de chaîne ne porterait
     * pas sur la colonne. Partout ailleurs, les modifieurs se composent dans
     * n'importe quel ordre, ce qui rend l'ajout en fin de ligne sûr.
     */
    private static function isForeignKey(string $definition): bool
    {
        return str_contains($definition, '->constrained(');
    }
}
