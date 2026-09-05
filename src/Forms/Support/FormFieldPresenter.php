<?php

declare(strict_types=1);

namespace Baobab\Forms\Support;

/**
 * Met en forme les champs d'un blueprint pour l'affichage — un seul point de
 * calcul pour l'aperçu du builder (Pass B2) et le rendu public (Pass C1),
 * qui partagent le même composant `<x-baobab::forms.fields>` (spec 14 §3 :
 * « aperçu rendu avec le vrai composant front »). Extrait de
 * `FormsController::previewFields()`, comportement inchangé.
 */
final class FormFieldPresenter
{
    /**
     * @param  list<array<string, mixed>>  $fields
     * @return list<array<string, mixed>>
     */
    public static function present(array $fields): array
    {
        return array_map(function (array $field): array {
            /** @var list<string> $choices */
            $choices = (array) ($field['options']['choices'] ?? []);
            // `consent` affiche son texte légal en priorité (§2.2) ; tous les
            // autres retombent sur le libellé saisi puis sur la clé.
            $label = (string) ($field['type'] === 'consent' ? ($field['options']['text'] ?? $field['label'] ?? $field['key']) : ($field['label'] ?? $field['key']));

            return [
                ...$field,
                'choice_options' => array_combine($choices, $choices),
                // Convention de formulaire : un astérisque à côté du libellé
                // signale un champ requis — absent tant que ce n'est pas
                // calculé ici (les composants `field.*` ne le déduisent pas
                // de `required`, qu'ils ne reçoivent même pas).
                'display_label' => ($field['required'] ?? false) ? "{$label} *" : $label,
            ];
        }, $fields);
    }
}
