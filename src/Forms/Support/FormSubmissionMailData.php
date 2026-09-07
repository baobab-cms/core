<?php

declare(strict_types=1);

namespace Baobab\Forms\Support;

use Baobab\Forms\Models\Form;
use Baobab\Forms\Models\FormSubmission;

/**
 * Variables communes aux e-mails de la Pass E (spec 14 §8) — un seul endroit
 * pour transformer une soumission en texte lisible, partagé par l'e-mail de
 * notification (§8.2), la notification admin (§8.4) et l'accusé de réception
 * (§8.3). `fields_summary` reste volontairement du texte à plat : le langage
 * de placeholders des e-mails (spec 13 §3.3) est délibérément pauvre — pas de
 * boucle, toujours échappé — impossible d'y faire tenir une liste de champs
 * dont les clés varient d'un formulaire à l'autre.
 *
 * Lit `blueprint_snapshot` plutôt que `$form->blueprint` : les deux
 * coïncident au moment de l'envoi (Pass E s'exécute juste après la
 * construction de la soumission), mais le snapshot est la source que la
 * Pass B5 a choisie pour rester fidèle à ce que le soumetteur a réellement
 * rempli.
 */
final class FormSubmissionMailData
{
    /**
     * @return array{form_title: string, submitted_at: string, fields_summary: string}
     */
    public static function build(Form $form, FormSubmission $submission): array
    {
        return [
            'form_title' => $form->title,
            'submitted_at' => now()->format('d/m/Y H:i'),
            'fields_summary' => self::fieldsSummary($submission),
        ];
    }

    /**
     * L'adresse du soumetteur, pour l'accusé de réception (spec 14 §8.3,
     * « optionnel, si un champ email existe ») — le premier champ `email` du
     * blueprint, patron `firstWhere('type', 'consent')` déjà tenu par
     * `SubmitForm` pour le champ de consentement.
     */
    public static function submitterEmail(FormSubmission $submission): ?string
    {
        /** @var array<string, mixed>|null $emailField */
        $emailField = collect((array) $submission->blueprint_snapshot)->firstWhere('type', 'email');

        if ($emailField === null) {
            return null;
        }

        $value = (string) (((array) $submission->payload)[(string) $emailField['key']] ?? '');

        return $value !== '' ? $value : null;
    }

    private static function fieldsSummary(FormSubmission $submission): string
    {
        /** @var list<array<string, mixed>> $fields */
        $fields = (array) $submission->blueprint_snapshot;

        /** @var array<string, mixed> $payload */
        $payload = (array) $submission->payload;

        $lines = [];

        foreach ($fields as $field) {
            $key = (string) ($field['key'] ?? '');

            if ($key === '' || ! array_key_exists($key, $payload)) {
                continue;
            }

            $label = (string) ($field['label'] ?? $key);
            $lines[] = $label.' : '.self::valueToText($payload[$key]);
        }

        return implode("\n", $lines);
    }

    private static function valueToText(mixed $value): string
    {
        if (is_array($value)) {
            // Champ fichier (Pass C3) : métadonnées ou référence stockée,
            // jamais le contenu du fichier lui-même.
            return (string) ($value['original_name'] ?? implode(', ', array_map(strval(...), $value)));
        }

        if (is_bool($value)) {
            return $value ? 'Oui' : 'Non';
        }

        return (string) $value;
    }
}
