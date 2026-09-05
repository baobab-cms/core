<?php

use Baobab\Audit\Models\AuditEntry;
use Baobab\Facades\Hook;
use Baobab\Forms\Actions\SaveForm;
use Baobab\Forms\Exceptions\DuplicateFormSlugException;
use Baobab\Forms\Models\Form;

it('creates a form at version 1 and audits it', function () {
    $form = app(SaveForm::class)(null, [
        'slug' => 'contact',
        'title' => 'Contact',
        'fields' => [
            ['key' => 'email', 'type' => 'email', 'required' => true],
        ],
    ]);

    expect($form->version)->toBe(1)
        ->and($form->slug)->toBe('contact')
        ->and($form->blueprint['fields'])->toHaveCount(1)
        ->and($form->store_submissions)->toBeTrue()
        ->and($form->retention_days)->toBe(365);

    expect(AuditEntry::where('action', 'form.created')->where('auditable_id', $form->id)->exists())->toBeTrue();
});

it('bumps the version on every save, even when only the title changes (spec 14 §3, to the letter)', function () {
    $form = app(SaveForm::class)(null, [
        'slug' => 'contact',
        'title' => 'Contact',
        'fields' => [['key' => 'email', 'type' => 'email', 'required' => true]],
    ]);

    $updated = app(SaveForm::class)($form, [
        'slug' => 'contact',
        'title' => 'Nous contacter',
        'fields' => [['key' => 'email', 'type' => 'email', 'required' => true]],
    ]);

    expect($updated->version)->toBe(2)
        ->and($updated->title)->toBe('Nous contacter');

    expect(AuditEntry::where('action', 'form.updated')->where('auditable_id', $form->id)->exists())->toBeTrue();
});

it('refuses a slug already used by another form', function () {
    app(SaveForm::class)(null, ['slug' => 'contact', 'title' => 'Contact', 'fields' => []]);

    expect(fn () => app(SaveForm::class)(null, ['slug' => 'contact', 'title' => 'Autre', 'fields' => []]))
        ->toThrow(DuplicateFormSlugException::class);
});

it('allows a form to keep its own slug when saved again', function () {
    $form = app(SaveForm::class)(null, ['slug' => 'contact', 'title' => 'Contact', 'fields' => []]);

    $updated = app(SaveForm::class)($form, ['slug' => 'contact', 'title' => 'Contact v2', 'fields' => []]);

    expect($updated->id)->toBe($form->id);
});

it('dispatches baobab.form.created and baobab.form.updated', function () {
    $createdReceived = null;
    $updatedReceived = null;

    Hook::listen('baobab.form.created', function (Form $form) use (&$createdReceived): void {
        $createdReceived = $form;
    });
    Hook::listen('baobab.form.updated', function (Form $form) use (&$updatedReceived): void {
        $updatedReceived = $form;
    });

    $form = app(SaveForm::class)(null, ['slug' => 'contact', 'title' => 'Contact', 'fields' => []]);
    app(SaveForm::class)($form, ['slug' => 'contact', 'title' => 'Contact v2', 'fields' => []]);

    expect($createdReceived)->not->toBeNull()
        ->and($updatedReceived)->not->toBeNull();
});
