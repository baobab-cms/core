<?php

declare(strict_types=1);

namespace Baobab\Forms\Support;

use Baobab\ContentTypes\Fields\FieldRegistry;
use Baobab\Forms\Models\Form;
use Illuminate\Validation\Rule;

/**
 * Construction des règles de validation Laravel d'une soumission depuis le
 * blueprint du formulaire (spec 14 §5) — patron
 * `Baobab\ContentTypes\Support\ContentEntryRules`, en plus simple : pas de
 * relations, pas de colonne `slug`/`unpublish_at`. Le honeypot n'y figure
 * jamais (§2.2, résolu par l'anti-spam en Pass D, pas par la validation).
 */
final class FormEntryRules
{
    public function __construct(private readonly FieldRegistry $fields) {}

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(Form $form): array
    {
        $rules = [];

        foreach ((array) ($form->blueprint['fields'] ?? []) as $field) {
            $key = $field['key'];
            $required = (bool) ($field['required'] ?? false);

            if ($field['type'] === 'consent') {
                $rules[$key] = $required ? ['accepted'] : ['nullable', 'boolean'];

                continue;
            }

            $fieldType = $this->fields->resolve(FormFieldTypes::registryKey($field['type']));
            $options = (array) ($field['options'] ?? []);

            $rules[$key] = array_merge(
                $required ? ['required'] : ['nullable'],
                $fieldType->rules($key, $options),
            );

            if ($field['type'] === 'checkboxes') {
                $rules["{$key}.*"] = [Rule::in((array) ($options['choices'] ?? []))];
            }
        }

        return $rules;
    }
}
