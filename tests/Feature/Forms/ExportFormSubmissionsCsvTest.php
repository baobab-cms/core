<?php

use Baobab\Forms\Actions\ExportFormSubmissionsCsv;
use Baobab\Forms\Actions\SaveForm;
use Baobab\Forms\Models\FormSubmission;

it('exports submissions as CSV, columns from the current blueprint', function () {
    $form = app(SaveForm::class)(null, [
        'slug' => 'contact',
        'title' => 'Contact',
        'fields' => [
            ['key' => 'full_name', 'type' => 'text'],
            ['key' => 'email', 'type' => 'email'],
        ],
    ]);

    FormSubmission::create([
        'form_id' => $form->id, 'form_version' => 1,
        'payload' => ['full_name' => 'Jane Doe', 'email' => 'jane@example.com'], 'status' => 'new',
    ]);

    $csv = app(ExportFormSubmissionsCsv::class)($form, FormSubmission::query()->where('form_id', $form->id));

    expect($csv)->toContain('full_name,email,status,submitted_at')
        ->toContain('"Jane Doe",jane@example.com,new');
});

it('only exports the rows the given query already filtered', function () {
    $form = app(SaveForm::class)(null, ['slug' => 'contact', 'title' => 'Contact', 'fields' => [['key' => 'email', 'type' => 'email']]]);

    FormSubmission::create(['form_id' => $form->id, 'form_version' => 1, 'payload' => ['email' => 'new@example.com'], 'status' => 'new']);
    FormSubmission::create(['form_id' => $form->id, 'form_version' => 1, 'payload' => ['email' => 'spam@example.com'], 'status' => 'spam']);

    $csv = app(ExportFormSubmissionsCsv::class)($form, FormSubmission::query()->where('form_id', $form->id)->where('status', 'spam'));

    expect($csv)->toContain('spam@example.com')
        ->not->toContain('new@example.com');
});

it('exports the original filename for a file field, never the raw stored reference (Pass C3)', function () {
    $form = app(SaveForm::class)(null, ['slug' => 'contact', 'title' => 'Contact', 'fields' => [['key' => 'cv', 'type' => 'file']]]);
    FormSubmission::create([
        'form_id' => $form->id, 'form_version' => 1,
        'payload' => ['cv' => ['original_name' => 'cv.pdf', 'stored_path' => 'form-submissions/2026/09/x.pdf']],
        'status' => 'new',
    ]);

    $csv = app(ExportFormSubmissionsCsv::class)($form, FormSubmission::query()->where('form_id', $form->id));

    expect($csv)->toContain('cv.pdf')
        ->not->toContain('form-submissions/2026/09/x.pdf');
});

it('exports an empty cell for a file field never filled in', function () {
    $form = app(SaveForm::class)(null, ['slug' => 'contact', 'title' => 'Contact', 'fields' => [['key' => 'cv', 'type' => 'file', 'required' => false]]]);
    FormSubmission::create(['form_id' => $form->id, 'form_version' => 1, 'payload' => [], 'status' => 'new']);

    $csv = app(ExportFormSubmissionsCsv::class)($form, FormSubmission::query()->where('form_id', $form->id));

    expect($csv)->toContain("cv,status,submitted_at\n,new,");
});
