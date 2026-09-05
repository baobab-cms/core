<?php

use Baobab\Audit\Models\AuditEntry;
use Baobab\Facades\Hook;
use Baobab\Forms\Actions\DeleteForm;
use Baobab\Forms\Actions\SaveForm;
use Baobab\Forms\Models\Form;
use Baobab\Forms\Models\FormSubmission;

it('deletes a form and its submissions cascade with it', function () {
    $form = app(SaveForm::class)(null, [
        'slug' => 'contact',
        'title' => 'Contact',
        'fields' => [['key' => 'email', 'type' => 'email', 'required' => true]],
    ]);

    FormSubmission::create([
        'form_id' => $form->id,
        'form_version' => $form->version,
        'payload' => ['email' => 'jane@example.com'],
        'status' => 'new',
    ]);

    app(DeleteForm::class)($form);

    expect(Form::find($form->id))->toBeNull()
        ->and(FormSubmission::where('form_id', $form->id)->exists())->toBeFalse();
});

it('audits the deletion and dispatches baobab.form.deleted', function () {
    $form = app(SaveForm::class)(null, ['slug' => 'contact', 'title' => 'Contact', 'fields' => []]);
    $formId = $form->id;

    $received = null;
    Hook::listen('baobab.form.deleted', function (string $slug) use (&$received): void {
        $received = $slug;
    });

    app(DeleteForm::class)($form);

    expect(AuditEntry::where('action', 'form.deleted')->where('auditable_id', $formId)->exists())->toBeTrue()
        ->and($received)->toBe('contact');
});
