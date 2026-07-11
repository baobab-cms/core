<?php

declare(strict_types=1);

namespace Baobab\ContentTypes\Evolution;

/**
 * Diff pur entre deux tableaux `fields[]` de blueprint (spec 02 §2.2).
 * Un champ renommé se déclare explicitement côté nouveau blueprint via
 * `renamed_from` — aucune heuristique de détection automatique, pour rester
 * déterministe (l'ambiguïté "renommage ou suppression+ajout ?" n'est pas
 * résoluble sans intention explicite).
 *
 * @phpstan-type FieldArray array<string, mixed>
 */
final class BlueprintDiffer
{
    /**
     * @param  list<FieldArray>  $oldFields
     * @param  list<FieldArray>  $newFields
     * @return array{
     *     added: list<FieldArray>,
     *     removed: list<FieldArray>,
     *     renamed: list<array{from: string, to: FieldArray}>,
     *     type_changed: list<array{key: string, from_type: string, from: FieldArray, to: FieldArray}>,
     * }
     */
    public function diff(array $oldFields, array $newFields): array
    {
        $oldByKey = [];
        foreach ($oldFields as $field) {
            $oldByKey[$field['key']] = $field;
        }

        $newByKey = [];
        foreach ($newFields as $field) {
            $newByKey[$field['key']] = $field;
        }

        $renamed = [];
        $renamedFromKeys = [];
        foreach ($newFields as $field) {
            if (isset($field['renamed_from'])) {
                $renamed[] = ['from' => (string) $field['renamed_from'], 'to' => $field];
                $renamedFromKeys[] = $field['renamed_from'];
            }
        }

        $added = [];
        foreach ($newFields as $field) {
            if (isset($field['renamed_from']) || isset($oldByKey[$field['key']])) {
                continue;
            }
            $added[] = $field;
        }

        $removed = [];
        foreach ($oldFields as $field) {
            if (in_array($field['key'], $renamedFromKeys, true) || isset($newByKey[$field['key']])) {
                continue;
            }
            $removed[] = $field;
        }

        $typeChanged = [];
        foreach ($newFields as $field) {
            if (isset($field['renamed_from'])) {
                continue;
            }

            $old = $oldByKey[$field['key']] ?? null;

            if ($old !== null && $old['type'] !== $field['type']) {
                $typeChanged[] = [
                    'key' => (string) $field['key'],
                    'from_type' => (string) $old['type'],
                    'from' => $old,
                    'to' => $field,
                ];
            }
        }

        return [
            'added' => $added,
            'removed' => $removed,
            'renamed' => $renamed,
            'type_changed' => $typeChanged,
        ];
    }
}
