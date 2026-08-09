<?php

declare(strict_types=1);

namespace Baobab\Studio\Support;

/**
 * Normalisation d'un bloc `fields[]` de blueprint saisi au wizard.
 *
 * Partagée par l'étape 2 (`entities[].fields`) et l'étape 7
 * (`widgets[].settings_fields`) : les deux blocs ont **la même forme**, ce que
 * le moteur reconnaît déjà — `ModuleBlueprint::validateWidgets()` réutilise
 * telle quelle `validateFields()` depuis la Pass A3c, et `WidgetGenerator`
 * dérive `settingsSchema()` des mêmes clés. La saisie suit la validation :
 * un seul normaliseur, pas deux qui divergeront.
 *
 * Aucune validation sémantique ici (types, options) — elle est faite en un
 * seul point par la cross-validation de `SaveStudioWizardStep`.
 */
final class BlueprintFields
{
    /** Types de champs dont `FieldType::optionsRules()` exige `choices` — le formulaire doit les saisir. */
    public const TYPES_NEEDING_CHOICES = ['select', 'multiselect', 'radio'];

    /**
     * @return list<array<string, mixed>>
     */
    public static function normalize(mixed $fields): array
    {
        if (! is_array($fields)) {
            return [];
        }

        $normalized = [];

        foreach ($fields as $field) {
            if (! is_array($field)) {
                continue;
            }

            $entry = [
                'key' => trim((string) ($field['key'] ?? '')),
                'type' => trim((string) ($field['type'] ?? '')),
                'required' => (bool) ($field['required'] ?? false),
                'unique' => (bool) ($field['unique'] ?? false),
                'indexed' => (bool) ($field['indexed'] ?? false),
            ];

            // Seul `choices` est exposé par le formulaire (obligatoire pour
            // select/multiselect/radio) ; les autres options de type restent
            // à leurs défauts, toutes `nullable`. Un bloc `options` vide n'est
            // pas écrit, pour ne pas polluer le blueprint.
            $choices = self::normalizeChoices($field['options']['choices'] ?? null);

            if ($choices !== []) {
                $entry['options'] = ['choices' => $choices];
            }

            $normalized[] = $entry;
        }

        return $normalized;
    }

    /**
     * @return list<string>
     */
    private static function normalizeChoices(mixed $choices): array
    {
        if (! is_array($choices)) {
            return [];
        }

        $normalized = [];

        foreach ($choices as $choice) {
            $value = trim((string) $choice);

            if ($value !== '') {
                $normalized[] = $value;
            }
        }

        return $normalized;
    }
}
