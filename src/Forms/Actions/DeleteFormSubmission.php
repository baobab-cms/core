<?php

declare(strict_types=1);

namespace Baobab\Forms\Actions;

use Baobab\Audit\AuditLogger;
use Baobab\Forms\Models\FormSubmission;

/**
 * Supprime une soumission (spec 14 §6.2) — auditée, ce sont des données
 * personnelles. Les fichiers joints ne sont pas encore purgés ici : leur
 * stockage n'existe pas avant la Pass C (rendu front), même remarque que
 * `FormSubmissionsPurgeCommand`.
 */
final class DeleteFormSubmission
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function __invoke(FormSubmission $submission): void
    {
        $formId = $submission->form_id;

        $submission->delete();

        $this->audit->record('form_submission.deleted', $submission, ['form_id' => $formId]);
    }
}
