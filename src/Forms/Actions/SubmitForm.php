<?php

declare(strict_types=1);

namespace Baobab\Forms\Actions;

use Baobab\Facades\Hook;
use Baobab\Forms\FormSubmissionStatus;
use Baobab\Forms\Models\Form;
use Baobab\Forms\Models\FormSubmission;
use Baobab\Forms\Support\FormEntryRules;
use Illuminate\Support\Facades\Validator;

/**
 * Valide et enregistre une soumission (spec 14 §5-6). Action publique : pas
 * d'acteur, pas d'audit — auditer chaque soumission anonyme n'a pas de sens
 * (spec 14 ne le demande à aucun endroit, contrairement à l'export ou la
 * suppression en §6.2).
 *
 * Ce que cette Action ne fait **pas**, volontairement, par découpage de
 * passe : l'anti-spam (Pass D — honeypot, piège temporel, captcha) et le
 * pipeline de suites (Pass E — e-mail, notification, webhook, hook
 * `baobab.form.submitted`). Elle est le socle sur lequel les deux se
 * brancheront, pas leur remplacement.
 */
final class SubmitForm
{
    public function __construct(private readonly FormEntryRules $rules) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function __invoke(Form $form, array $input, ?string $ip = null): FormSubmission
    {
        /** @var array<string, list<mixed>> $rules */
        $rules = Hook::filter('baobab.form.validating', $this->rules->rules($form), $form);

        $validated = Validator::make($input, $rules)->validate();

        /** @var array<string, mixed>|null $consentField */
        $consentField = collect((array) ($form->blueprint['fields'] ?? []))->firstWhere('type', 'consent');
        $consentAt = $consentField !== null && (bool) ($validated[(string) $consentField['key']] ?? false)
            ? now()
            : null;

        $submission = new FormSubmission([
            'form_id' => $form->id,
            'form_version' => $form->version,
            'blueprint_snapshot' => $form->blueprint['fields'] ?? [],
            'payload' => $validated,
            'consent_at' => $consentAt,
            'ip' => $form->retain_ip ? $ip : null,
            'status' => FormSubmissionStatus::New->value,
        ]);

        if ($form->store_submissions) {
            $submission->save();
        }

        return $submission;
    }
}
