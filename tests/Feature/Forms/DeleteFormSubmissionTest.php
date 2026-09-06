<?php

use Baobab\Audit\Models\AuditEntry;
use Baobab\Forms\Actions\DeleteFormSubmission;
use Baobab\Forms\Actions\SaveForm;
use Baobab\Forms\Models\FormSubmission;
use Illuminate\Support\Facades\Storage;

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

it('deletes the attached file from the private disk along with the submission (spec 14 §5, Pass C3)', function () {
    Storage::fake('local');
    Storage::disk('local')->put('form-submissions/2026/09/test.pdf', 'contenu');

    $form = app(SaveForm::class)(null, [
        'slug' => 'contact',
        'title' => 'Contact',
        'fields' => [['key' => 'cv', 'type' => 'file']],
    ]);

    $submission = FormSubmission::create([
        'form_id' => $form->id,
        'form_version' => 1,
        'blueprint_snapshot' => $form->blueprint['fields'],
        'payload' => ['cv' => [
            'original_name' => 'cv.pdf',
            'stored_path' => 'form-submissions/2026/09/test.pdf',
            'mime_type' => 'application/pdf',
            'size' => 7,
        ]],
        'status' => 'new',
    ]);

    app(DeleteFormSubmission::class)($submission);

    Storage::disk('local')->assertMissing('form-submissions/2026/09/test.pdf');
});

it('does not fail when a file field was left empty (no attachment to delete)', function () {
    $form = app(SaveForm::class)(null, [
        'slug' => 'contact',
        'title' => 'Contact',
        'fields' => [['key' => 'cv', 'type' => 'file', 'required' => false]],
    ]);

    $submission = FormSubmission::create([
        'form_id' => $form->id,
        'form_version' => 1,
        'blueprint_snapshot' => $form->blueprint['fields'],
        'payload' => [],
        'status' => 'new',
    ]);

    app(DeleteFormSubmission::class)($submission);

    expect(FormSubmission::find($submission->id))->toBeNull();
});
