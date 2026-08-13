<?php

declare(strict_types=1);

namespace Baobab\Studio\Generator\Support;

use Baobab\ContentTypes\Fields\FieldRegistry;
use Illuminate\Support\Str;

/**
 * Filtre et convertit un `entity[]` de blueprint Wizard Studio en éléments
 * réutilisés identiquement par les trois générateurs de surface (admin,
 * front, API — M8 point 1 Pass A2) : quels champs exposer, et sous quels
 * noms de vue/route dérivés de la clé d'entité.
 *
 * **Narrowing assumé, partagé par les trois surfaces** : seuls les types de
 * champ dont le composant de formulaire suit le contrat uniforme
 * `name`/`label`/`value` sont exposés. Les autres restent en base (leur
 * colonne, leur cast et leur `fillable` sont émis par A1 quoi qu'il arrive)
 * mais hors des CRUD générés.
 *
 * **Ne restent exclus que `gallery` et `media`, et la raison n'est pas celle
 * qu'on croit** (précision apportée en livrant le n° 117, l'ancienne
 * rédaction disant « sélecteur de média, hors périmètre » laissait entendre
 * qu'il manquait un composant). Les deux composants existent, et ils sont
 * autonomes : `mediaFieldInstance` est défini dans le composant lui-même par
 * un `@once`, le sélecteur tape les routes média globales de l'admin, et les
 * vues générées héritent du layout d'administration. Ce qui bloque est un
 * **contrat de props** : `field.media` attend `:media`, une instance de
 * `Media` dont il lit `id`/`url()`/`file_name`/`mime_type`/`alt`, et
 * `field.gallery` attend `:items`, une collection — là où le contrôleur
 * généré fournit un identifiant brut sous `value`. Les inclure suppose donc
 * une hydratation dans le contrôleur généré, c'est-à-dire une dépendance
 * nouvelle des modules générés envers le domaine Médias. Confié au point 2
 * (fusion des chemins de code), qui transposera l'hydratation que la fiche de
 * Content Type sait déjà faire, plutôt que de l'écrire deux fois.
 *
 * `multiselect` en est sorti au n° 117, son composant ayant été construit.
 */
final class EntityFields
{
    private const array EXCLUDED_TYPES = ['gallery', 'media'];

    /**
     * @param  array<string, mixed>  $entity
     * @return list<array<string, mixed>>
     */
    public static function included(array $entity): array
    {
        return array_values(array_filter(
            (array) ($entity['fields'] ?? []),
            fn (array $field): bool => ! in_array($field['type'], self::EXCLUDED_TYPES, true),
        ));
    }

    /**
     * Clés des champs inclus d'un type donné, écrites en littéral PHP —
     * consommées par le Form Request généré, qui doit normaliser avant
     * validation ce que la saisie HTML n'envoie pas sous la forme attendue par
     * la règle du type (n° 117) : du JSON en chaîne pour un `json`, rien du
     * tout pour une case décochée, `H:i` sans les secondes pour un `time`.
     *
     * @param  array<string, mixed>  $entity
     */
    public static function keysLiteral(array $entity, string $type): string
    {
        return collect(self::included($entity))
            ->filter(fn (array $field): bool => $field['type'] === $type)
            ->map(fn (array $field): string => "'{$field['key']}'")
            ->implode(', ');
    }

    /**
     * @param  array<string, mixed>  $entity
     */
    public static function viewPrefix(array $entity): string
    {
        return Str::snake(Str::plural((string) $entity['key']));
    }

    /**
     * @param  array<string, mixed>  $entity
     */
    public static function title(array $entity): string
    {
        return Str::headline(Str::plural((string) $entity['key']));
    }

    /**
     * Règles de validation littérales des champs inclus — patron
     * `Baobab\ContentTypes\Support\ContentEntryRules::rules()` (`required`/
     * `nullable` fusionné avec `FieldType::rules()`), mais écrites en dur
     * dans le fichier généré au lieu d'être recalculées à l'exécution.
     * Partagée par le Form Request admin et le contrôleur API (même entité,
     * mêmes règles, un seul `{Key}Request`).
     *
     * @param  array<string, mixed>  $entity
     */
    public static function validationRulesLiteral(array $entity, FieldRegistry $fields): string
    {
        return collect(self::included($entity))
            ->map(function (array $field) use ($fields): string {
                $fieldType = $fields->resolve($field['type']);
                $typeRules = $fieldType->rules((string) $field['key'], $field['options'] ?? []);
                $required = (bool) ($field['required'] ?? false);

                $rules = array_merge($required ? ['required'] : ['nullable'], $typeRules);
                $rulesLiteral = collect($rules)->map(fn (string $rule): string => "'{$rule}'")->implode(', ');

                return "            '{$field['key']}' => [{$rulesLiteral}],";
            })
            ->implode("\n");
    }
}
