<?php

declare(strict_types=1);

namespace Baobab\Privacy\Providers;

use Baobab\Forms\Models\FormSubmission;
use Baobab\Forms\Support\FormFileStorage;
use Baobab\Privacy\DataDeclaration;
use Baobab\Privacy\EraseOutcome;
use Baobab\Privacy\EraseReport;
use Baobab\Privacy\PersonalDataExport;
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
            if ($this->belongsTo($submission, $email)) {
                return true;
            }
        }

        return false;
    }

    /** Rien ne s'oppose à la suppression : les soumissions et leurs fichiers partent. */
    public function erase(Subject $subject): EraseReport
    {
        $email = (string) $this->emailOf($subject);
        $files = new FormFileStorage;
        $count = 0;

        foreach (FormSubmission::query()->lazyById(200) as $submission) {
            if (! $this->belongsTo($submission, $email)) {
                continue;
            }

            $files->deleteForSubmission($submission);
            $submission->delete();
            $count++;
        }

        return new EraseReport(EraseOutcome::Deleted, $count, __('baobab::privacy.erasure.form_submissions_note'));
    }

    /**
     * Les réponses du sujet, telles que saisies, et ses pièces jointes. Une
     * pièce jointe est remplacée dans `payload` par son nom dans l'archive :
     * le chemin de stockage interne n'a aucun sens hors de ce serveur.
     */
    public function export(Subject $subject): PersonalDataExport
    {
        $email = (string) $this->emailOf($subject);
        $submissions = [];
        $files = [];

        foreach (FormSubmission::query()->with('form')->lazyById(200) as $submission) {
            if (! $this->belongsTo($submission, $email)) {
                continue;
            }

            $payload = $submission->payload;

            foreach ((array) $submission->blueprint_snapshot as $field) {
                $key = (string) ($field['key'] ?? '');
                $value = $payload[$key] ?? null;

                if (($field['type'] ?? null) !== 'file' || ! is_array($value)) {
                    continue;
                }

                $archiveName = "submission-{$submission->id}-".basename((string) ($value['original_name'] ?? $key));
                $files[$archiveName] = ['disk' => (string) config('baobab.forms.disk', 'local'), 'path' => (string) ($value['stored_path'] ?? '')];
                $payload[$key] = ['file' => $archiveName, 'original_name' => $value['original_name'] ?? null, 'mime_type' => $value['mime_type'] ?? null, 'size' => $value['size'] ?? null];
            }

            $submissions[] = [
                'form' => $submission->form->title ?? null,
                'submitted_at' => $submission->created_at->toIso8601String(),
                'payload' => $payload,
                'consent_at' => $submission->consent_at?->toIso8601String(),
                'ip' => $submission->ip,
                'status' => $submission->status->value,
            ];
        }

        return new PersonalDataExport(['submissions' => $submissions], $files);
    }

    private function belongsTo(FormSubmission $submission, string $email): bool
    {
        foreach ($submission->blueprint_snapshot ?? [] as $field) {
            if (($field['type'] ?? null) !== 'email') {
                continue;
            }

            $value = $submission->payload[$field['key'] ?? ''] ?? null;

            if (is_string($value) && mb_strtolower(trim($value)) === $email) {
                return true;
            }
        }

        return false;
    }
}
