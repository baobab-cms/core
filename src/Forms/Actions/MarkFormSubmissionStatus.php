<?php

declare(strict_types=1);

namespace Baobab\Forms\Actions;

use Baobab\Forms\FormSubmissionStatus;
use Baobab\Forms\Models\FormSubmission;

/**
 * Marque une soumission lue / spam / archivée (spec 14 §6.2). Non auditée :
 * la spec ne nomme que la suppression et l'export comme audités, un
 * changement de statut n'a pas la même portée sur des données personnelles.
 */
final class MarkFormSubmissionStatus
{
    public function __invoke(FormSubmission $submission, FormSubmissionStatus $status): FormSubmission
    {
        $submission->update(['status' => $status->value]);

        return $submission->fresh() ?? $submission;
    }
}
