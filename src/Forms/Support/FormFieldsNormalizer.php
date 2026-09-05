<?php

declare(strict_types=1);

namespace Baobab\Forms\Support;

/**
 * Normalisation d'un bloc `fields[]` saisi au constructeur admin (M8 point 6,
 * Pass B2) — patron `Baobab\Studio\Support\BlueprintFields`, adapté : pas de
 * `unique`/`indexed` (sans objet, les soumissions vivent en JSON) ; `label`/
 * `placeholder`/`help_text` en plus (spec 14 §2.2) ; `choices` couvre aussi
 * bien les types Core (`select`/`radio`) que l'alias formulaire
 * (`checkboxes`) ; options `text`/`privacy_url` propres au type `consent`.
 *
 * Comme `BlueprintFields`, ne reçoit que ce que le payload JS a déjà mis en
 * forme sous `options` — le client transforme `_choicesText`/`_consentText`
 * en `options.choices`/`options.text` avant sérialisation (les clés
 * d'interface préfixées `_` ne sortent jamais du composant Alpine).
 *
 * Aucune validation sémantique ici — `FormBlueprint::fromArray()` la fait en
 * un seul point (mêmes rôles que `BlueprintFields`/`SaveStudioWizardStep`).
 */
final class FormFieldsNormalizer
{
    public const TYPES_NEEDING_CHOICES = ['select', 'radio', 'checkboxes'];

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

            $type = trim((string) ($field['type'] ?? ''));
            /** @var array<string, mixed> $rawOptions */
            $rawOptions = is_array($field['options'] ?? null) ? $field['options'] : [];

            $entry = [
                'key' => trim((string) ($field['key'] ?? '')),
                'type' => $type,
                'label' => self::nullableString($field['label'] ?? null),
                'placeholder' => self::nullableString($field['placeholder'] ?? null),
                'help_text' => self::nullableString($field['help_text'] ?? null),
                'required' => (bool) ($field['required'] ?? false),
            ];

            $options = self::normalizeOptions($type, $rawOptions);

            if ($options !== []) {
                $entry['options'] = $options;
            }

            $normalized[] = $entry;
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $rawOptions
     * @return array<string, mixed>
     */
    private static function normalizeOptions(string $type, array $rawOptions): array
    {
        if ($type === 'consent') {
            $options = [];
            $text = self::nullableString($rawOptions['text'] ?? null);
            $privacyUrl = self::nullableString($rawOptions['privacy_url'] ?? null);

            if ($text !== null) {
                $options['text'] = $text;
            }

            if ($privacyUrl !== null) {
                $options['privacy_url'] = $privacyUrl;
            }

            return $options;
        }

        if (in_array($type, self::TYPES_NEEDING_CHOICES, true)) {
            return self::normalizeChoiceOptions($rawOptions['choices'] ?? null);
        }

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    private static function normalizeChoiceOptions(mixed $choices): array
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

        return $normalized === [] ? [] : ['choices' => $normalized];
    }

    private static function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
