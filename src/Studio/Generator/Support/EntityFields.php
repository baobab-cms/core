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
 * `name`/`label`/`value` sont exposés — `gallery`/`media` (sélecteur de
 * média, hors périmètre de cette passe) et `multiselect`
 * (`FieldType::formComponent()` déclare `baobab::field.multiselect` mais ce
 * composant n'existe pas encore, écart réel du Core découvert en construisant
 * la Pass A2) restent en base (héritées d'A1) mais hors des CRUD générés.
 */
final class EntityFields
{
    private const array EXCLUDED_TYPES = ['gallery', 'media', 'multiselect'];

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
