<?php

declare(strict_types=1);

namespace Baobab\Privacy\Providers;

use Baobab\Forms\Models\FormSubmission;
use Baobab\Privacy\DataDeclaration;
use Baobab\Privacy\Subject;

/**
 * `forms.submissions` (spec 16 §2.1, spec 14 §6.4). L'e-mail du sujet n'est
 * jamais dans une colonne : il vit dans `payload`, sous les clés des champs
 * de type `email` — que seul le `blueprint_snapshot` de chaque soumission
 * permet de connaître (les champs ont pu changer depuis). La recherche est
 * donc un balayage linéaire, arrêté au premier résultat ; acceptable pour un
 * `locate` ponctuel, à réévaluer si le volume l'exige.
 */
final class FormSubmissionsProvider extends CoreProvider
{
    public function key(): string
    {
        return 'forms.submissions';
    }

    public function describe(): DataDeclaration
    {
        return new DataDeclaration(
            title: __('baobab::privacy.form_submissions.title'),
            nature: __('baobab::privacy.form_submissions.nature'),
            purpose: __('baobab::privacy.form_submissions.purpose'),
            legalBasis: __('baobab::privacy.form_submissions.legal_basis'),
            retention: __('baobab::privacy.form_submissions.retention'),
            externalServices: $this->mailServices(),
        );
    }

    public function locate(Subject $subject): bool
    {
        $email = $this->emailOf($subject);

        if ($email === null) {
            return false;
        }

        foreach (FormSubmission::query()->select(['id', 'payload', 'blueprint_snapshot'])->lazyById(200) as $submission) {
            foreach ($submission->blueprint_snapshot ?? [] as $field) {
                if (($field['type'] ?? null) !== 'email') {
                    continue;
                }

                $value = $submission->payload[$field['key'] ?? ''] ?? null;

                if (is_string($value) && mb_strtolower(trim($value)) === $email) {
                    return true;
                }
            }
        }

        return false;
    }
}
