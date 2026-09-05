<?php

use Baobab\Audit\Models\AuditEntry;
use Baobab\Forms\Actions\DeleteFormSubmission;
use Baobab\Forms\Actions\SaveForm;
use Baobab\Forms\Models\FormSubmission;

it('deletes a submission and audits it — data personnelles (spec 14 §6.2)', function () {
    $form = app(SaveForm::class)(null, ['slug' => 'contact', 'title' => 'Contact', 'fields' => []]);
    $submission = FormSubmission::create([
        'form_id' => $form->id, 'form_version' => 1, 'payload' => ['email' => 'jane@example.com'], 'status' => 'new',
    ]);
    $submissionId = $submission->id;

    app(DeleteFormSubmission::class)($submission);

    expect(FormSubmission::find($submissionId))->toBeNull()
        ->and(AuditEntry::where('action', 'form_submission.deleted')->exists())->toBeTrue();
});
