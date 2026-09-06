<?php

declare(strict_types=1);

namespace Baobab\Forms\Actions;

use Baobab\Facades\Hook;
use Baobab\Forms\FormSubmissionStatus;
use Baobab\Forms\Models\Form;
use Baobab\Forms\Models\FormSubmission;
use Baobab\Forms\Support\FormEntryRules;
use Baobab\Forms\Support\FormFileStorage;
use Illuminate\Http\UploadedFile;
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
 *
 * Un champ `file` validé arrive ici comme un `UploadedFile` — jamais laissé
 * tel quel dans `payload` : le cast `array` d'Eloquent encode en JSON **dès
 * l'affectation** (`setAttribute()`, pas seulement à `save()`), qui ne sait
 * pas sérialiser un `UploadedFile` — construire le modèle plante même quand
 * `store_submissions` est faux et qu'il n'est jamais persisté. `remplace
 * toujours` par une valeur sérialisable ; seule l'écriture sur disque (par
 * `FormFileStorage`) est conditionnée à `store_submissions` (§6.3 : un
 * chemin jamais référencé nulle part serait un orphelin immédiat).
 */
final class SubmitForm
{
    public function __construct(
        private readonly FormEntryRules $rules,
        private readonly FormFileStorage $fileStorage,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function __invoke(Form $form, array $input, ?string $ip = null): FormSubmission
    {
        /** @var array<string, list<mixed>> $rules */
        $rules = Hook::filter('baobab.form.validating', $this->rules->rules($form), $form);

        $validated = Validator::make($input, $rules)->validate();
        $validated = $this->normalizeUploadedFiles($form, $validated);

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

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function normalizeUploadedFiles(Form $form, array $validated): array
    {
        foreach ((array) ($form->blueprint['fields'] ?? []) as $field) {
            if (($field['type'] ?? null) !== 'file') {
                continue;
            }

            $key = (string) $field['key'];
            $value = $validated[$key] ?? null;

            if (! $value instanceof UploadedFile) {
                continue;
            }

            $validated[$key] = $form->store_submissions
                ? $this->fileStorage->store($value)
                : [
                    'original_name' => (string) $value->getClientOriginalName(),
                    'mime_type' => (string) $value->getMimeType(),
                    'size' => (int) $value->getSize(),
                ];
        }

        return $validated;
    }
}
