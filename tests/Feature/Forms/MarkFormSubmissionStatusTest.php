<?php

use Baobab\Forms\Actions\MarkFormSubmissionStatus;
use Baobab\Forms\Actions\SaveForm;
use Baobab\Forms\FormSubmissionStatus;
use Baobab\Forms\Models\FormSubmission;

it('changes the status of a submission', function () {
    $form = app(SaveForm::class)(null, ['slug' => 'contact', 'title' => 'Contact', 'fields' => []]);
    $submission = FormSubmission::create([
        'form_id' => $form->id, 'form_version' => 1, 'payload' => [], 'status' => 'new',
    ]);

    $updated = app(MarkFormSubmissionStatus::class)($submission, FormSubmissionStatus::Spam);

    expect($updated->status)->toBe(FormSubmissionStatus::Spam)
        ->and($submission->fresh()->status)->toBe(FormSubmissionStatus::Spam);
});
