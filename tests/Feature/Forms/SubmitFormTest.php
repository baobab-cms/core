<?php

use Baobab\Facades\Hook;
use Baobab\Forms\Actions\SaveForm;
use Baobab\Forms\Actions\SubmitForm;
use Baobab\Forms\FormSubmissionStatus;
use Baobab\Forms\Models\FormSubmission;
use Baobab\Forms\Support\FormSpamGuard;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
        ->and($submission->status)->toBe(FormSubmissionStatus::New)
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

it('stores an uploaded file on the private disk and replaces it with a structured reference (spec 14 §5)', function () {
    Storage::fake('local');

    $form = app(SaveForm::class)(null, [
        'slug' => 'contact',
        'title' => 'Contact',
        'fields' => [['key' => 'cv', 'type' => 'file', 'required' => true]],
    ]);

    $file = UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf');

    $submission = app(SubmitForm::class)($form, ['cv' => $file]);

    expect($submission->payload['cv'])->toBeArray()
        ->and($submission->payload['cv']['original_name'])->toBe('cv.pdf')
        ->and($submission->payload['cv']['mime_type'])->toBe('application/pdf')
        ->and($submission->payload['cv']['stored_path'])->toBeString();

    Storage::disk('local')->assertExists($submission->payload['cv']['stored_path']);
});

it('rejects a file whose real content type is not in the allowed list', function () {
    $form = app(SaveForm::class)(null, [
        'slug' => 'contact',
        'title' => 'Contact',
        'fields' => [['key' => 'cv', 'type' => 'file', 'required' => true]],
    ]);

    $file = UploadedFile::fake()->create('script.exe', 10, 'application/x-msdownload');

    expect(fn () => app(SubmitForm::class)($form, ['cv' => $file]))->toThrow(ValidationException::class);
});

it('rejects a file larger than the configured maximum', function () {
    $form = app(SaveForm::class)(null, [
        'slug' => 'contact',
        'title' => 'Contact',
        'fields' => [['key' => 'cv', 'type' => 'file', 'required' => true]],
    ]);

    config(['baobab.forms.max_upload_size' => 1024]);
    $file = UploadedFile::fake()->create('cv.pdf', 5, 'application/pdf');

    expect(fn () => app(SubmitForm::class)($form, ['cv' => $file]))->toThrow(ValidationException::class);
});

it('honors a per-field mime_types override tighter than the global default', function () {
    $form = app(SaveForm::class)(null, [
        'slug' => 'contact',
        'title' => 'Contact',
        'fields' => [['key' => 'cv', 'type' => 'file', 'required' => true, 'options' => ['mime_types' => ['application/pdf']]]],
    ]);

    $file = UploadedFile::fake()->create('photo.png', 10, 'image/png');

    expect(fn () => app(SubmitForm::class)($form, ['cv' => $file]))->toThrow(ValidationException::class);
});

it('never writes the file to disk when the form opts out of storage (§6.3)', function () {
    Storage::fake('local');

    $form = app(SaveForm::class)(null, [
        'slug' => 'contact',
        'title' => 'Contact',
        'fields' => [['key' => 'cv', 'type' => 'file', 'required' => true]],
        'store_submissions' => false,
    ]);

    $file = UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf');

    app(SubmitForm::class)($form, ['cv' => $file]);

    expect(Storage::disk('local')->allFiles('form-submissions'))->toBeEmpty();
});

it('marks the submission as spam rather than rejecting it when the honeypot is filled (spec 14 §7.1-7.2)', function () {
    $form = app(SaveForm::class)(null, [
        'slug' => 'contact',
        'title' => 'Contact',
        'fields' => [['key' => 'email', 'type' => 'email', 'required' => true]],
    ]);

    $submission = app(SubmitForm::class)($form, [
        'email' => 'jane@example.com',
        FormSpamGuard::HONEYPOT_FIELD => 'i am a bot',
    ]);

    expect($submission->exists)->toBeTrue()
        ->and($submission->status)->toBe(FormSubmissionStatus::Spam)
        ->and($submission->payload)->toBe(['email' => 'jane@example.com']);
});

it('marks the submission as spam when it arrives faster than the render token allows', function () {
    $form = app(SaveForm::class)(null, [
        'slug' => 'contact', 'title' => 'Contact', 'fields' => [],
    ]);

    $submission = app(SubmitForm::class)($form, [
        FormSpamGuard::TIMESTAMP_FIELD => FormSpamGuard::renderToken(),
    ]);

    expect($submission->status)->toBe(FormSubmissionStatus::Spam);
});

it('lets baobab.form.spam_checking flag as spam a submission the level-1 guard let through (spec 14 §7.2, Pass D2)', function () {
    $form = app(SaveForm::class)(null, [
        'slug' => 'contact',
        'title' => 'Contact',
        'fields' => [['key' => 'email', 'type' => 'email', 'required' => true]],
    ]);

    Hook::modify('baobab.form.spam_checking', function (bool $isSpam, $hookedForm, array $payload) use ($form): bool {
        expect($hookedForm->id)->toBe($form->id)
            ->and($payload)->toBe(['email' => 'jane@example.com']);

        return true;
    });

    $submission = app(SubmitForm::class)($form, ['email' => 'jane@example.com']);

    expect($submission->status)->toBe(FormSubmissionStatus::Spam);
});

it('lets baobab.form.spam_checking clear a level-1 verdict, since it is a normal filter chain', function () {
    $form = app(SaveForm::class)(null, [
        'slug' => 'contact', 'title' => 'Contact', 'fields' => [],
    ]);

    Hook::modify('baobab.form.spam_checking', fn (): bool => false);

    $submission = app(SubmitForm::class)($form, [
        FormSpamGuard::HONEYPOT_FIELD => 'i am a bot',
    ]);

    expect($submission->status)->toBe(FormSubmissionStatus::New);
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
