<?php

use Baobab\Facades\Hook;
use Baobab\Forms\Actions\SaveForm;
use Baobab\Forms\Actions\SubmitForm;
use Baobab\Forms\FormSubmissionStatus;
use Baobab\Forms\Models\FormSubmission;
use Illuminate\Validation\ValidationException;

it('validates the payload against the blueprint and persists the submission', function () {
    $form = app(SaveForm::class)(null, [
        'slug' => 'contact',
        'title' => 'Contact',
        'fields' => [
            ['key' => 'full_name', 'type' => 'text', 'required' => true],
            ['key' => 'email', 'type' => 'email', 'required' => true],
        ],
    ]);

    $submission = app(SubmitForm::class)($form, [
        'full_name' => 'Jane Doe',
        'email' => 'jane@example.com',
    ]);

    expect($submission->exists)->toBeTrue()
        ->and($submission->form_id)->toBe($form->id)
        ->and($submission->form_version)->toBe($form->version)
        ->and($submission->status)->toBe(FormSubmissionStatus::New->value)
        ->and($submission->payload)->toBe(['full_name' => 'Jane Doe', 'email' => 'jane@example.com']);
});

it('rejects a submission missing a required field', function () {
    $form = app(SaveForm::class)(null, [
        'slug' => 'contact',
        'title' => 'Contact',
        'fields' => [['key' => 'email', 'type' => 'email', 'required' => true]],
    ]);

    expect(fn () => app(SubmitForm::class)($form, []))->toThrow(ValidationException::class);
});

it('does not persist a submission when the form opts out of storage (§6.3)', function () {
    $form = app(SaveForm::class)(null, [
        'slug' => 'contact',
        'title' => 'Contact',
        'fields' => [['key' => 'email', 'type' => 'email', 'required' => true]],
        'store_submissions' => false,
    ]);

    $submission = app(SubmitForm::class)($form, ['email' => 'jane@example.com']);

    expect($submission->exists)->toBeFalse()
        ->and($submission->payload)->toBe(['email' => 'jane@example.com'])
        ->and(FormSubmission::count())->toBe(0);
});

it('records consent_at only when the consent field is accepted', function () {
    $form = app(SaveForm::class)(null, [
        'slug' => 'contact',
        'title' => 'Contact',
        'fields' => [
            ['key' => 'email', 'type' => 'email', 'required' => true],
            ['key' => 'gdpr', 'type' => 'consent', 'options' => ['text' => 'J\'accepte.']],
        ],
    ]);

    $accepted = app(SubmitForm::class)($form, ['email' => 'jane@example.com', 'gdpr' => true]);
    $declined = app(SubmitForm::class)($form, ['email' => 'jane@example.com', 'gdpr' => false]);

    expect($accepted->consent_at)->not->toBeNull()
        ->and($declined->consent_at)->toBeNull();
});

it('retains the IP only when the form explicitly requires it (§6.4)', function () {
    $retaining = app(SaveForm::class)(null, [
        'slug' => 'retaining', 'title' => 'Retaining', 'fields' => [], 'retain_ip' => true,
    ]);
    $notRetaining = app(SaveForm::class)(null, [
        'slug' => 'not-retaining', 'title' => 'Not retaining', 'fields' => [], 'retain_ip' => false,
    ]);

    $withIp = app(SubmitForm::class)($retaining, [], '203.0.113.1');
    $withoutIp = app(SubmitForm::class)($notRetaining, [], '203.0.113.1');

    expect($withIp->ip)->toBe('203.0.113.1')
        ->and($withoutIp->ip)->toBeNull();
});

it('resolves the spec 14 §2.2 aliases (number/checkbox/checkboxes) to their FieldRegistry keys', function () {
    $form = app(SaveForm::class)(null, [
        'slug' => 'survey',
        'title' => 'Survey',
        'fields' => [
            ['key' => 'age', 'type' => 'number', 'required' => true],
            ['key' => 'subscribe', 'type' => 'checkbox'],
            ['key' => 'interests', 'type' => 'checkboxes', 'options' => ['choices' => ['php', 'js']]],
        ],
    ]);

    $submission = app(SubmitForm::class)($form, [
        'age' => 34,
        'subscribe' => true,
        'interests' => ['php'],
    ]);

    expect($submission->payload)->toBe(['age' => 34, 'subscribe' => true, 'interests' => ['php']]);

    expect(fn () => app(SubmitForm::class)($form, ['age' => 34, 'interests' => ['cobol']]))
        ->toThrow(ValidationException::class);
});

it('lets baobab.form.validating augment the rules before validation', function () {
    $form = app(SaveForm::class)(null, [
        'slug' => 'contact', 'title' => 'Contact', 'fields' => [],
    ]);

    Hook::modify('baobab.form.validating', function (array $rules) {
        $rules['honeypot_check'] = ['prohibited'];

        return $rules;
    });

    expect(fn () => app(SubmitForm::class)($form, ['honeypot_check' => 'i am a bot']))
        ->toThrow(ValidationException::class);
});
