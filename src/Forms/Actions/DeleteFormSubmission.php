<?php

declare(strict_types=1);

namespace Baobab\Forms\Actions;

use Baobab\Audit\AuditLogger;
use Baobab\Forms\Models\FormSubmission;
use Baobab\Forms\Support\FormFileStorage;

/**
 * Supprime une soumission (spec 14 §6.2) — auditée, ce sont des données
 * personnelles. Les pièces jointes (Pass C3) sont effacées du disque privé
 * **avant** la ligne, jamais l'inverse : si l'effacement du fichier échoue,
 * mieux vaut une ligne encore là (rejouable) qu'un fichier orphelin sans
 * plus aucune trace pour le retrouver.
 *
 * Lit `blueprint_snapshot` (via `FormFileStorage::deleteForSubmission()`),
 * jamais le blueprint courant du formulaire : une soumission sans snapshot
 * (antérieure à la Pass B5) ne peut de toute façon pas porter de champ
 * `file`, ce type n'existant pas avant la Pass C3.
 */
final class DeleteFormSubmission
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly FormFileStorage $fileStorage,
    ) {}

    public function __invoke(FormSubmission $submission): void
    {
        $formId = $submission->form_id;

        $this->fileStorage->deleteForSubmission($submission);

        $submission->delete();

        $this->audit->record('form_submission.deleted', $submission, ['form_id' => $formId]);
    }
}
