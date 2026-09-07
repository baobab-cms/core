<?php

use Baobab\Access\Actions\GrantPermission;
use Baobab\Facades\Hook;
use Baobab\Forms\Actions\SaveForm;
use Baobab\Forms\Actions\SubmitForm;
use Baobab\Forms\Support\FormSpamGuard;
use Baobab\Mail\Jobs\SendQueuedMail;
use Baobab\Mail\Models\MailLogEntry;
use Baobab\Notify\Notifications\BaobabNotification;
use Baobab\Users\Models\User;
use Baobab\Webhooks\Jobs\DeliverWebhook;
use Baobab\Webhooks\Models\WebhookSubscription;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

/**
 * Pipeline de suites (spec 14 §8, M8 point 6, Pass E) : étapes 2 à 6,
 * câblées par `BaobabServiceProvider::registerFormSuitesListener()` sur le
 * hook `baobab.form.submitted` que `SubmitForm` déclenche. Chaque étape est
 * testée indépendamment (suivi n° 278 : elles ne doivent jamais se couper
 * l'une l'autre, en particulier le webhook — dont le réglage a bien failli,
 * dans une version antérieure de cette passe, éteindre aussi les trois
 * autres en gating le hook lui-même).
 */
function formsSuitesActor(): User
{
    static $counter = 0;
    $counter++;

    return User::create([
        'name' => "Suites Actor {$counter}",
        'email' => "suites-actor-{$counter}@example.com",
        'password' => 'secret',
    ]);
}

it('sends the notification e-mail to every configured recipient (§8.2)', function () {
    Queue::fake();

    $form = app(SaveForm::class)(null, [
        'slug' => 'contact',
        'title' => 'Contact',
        'fields' => [['key' => 'email', 'type' => 'email', 'required' => true]],
        'settings' => ['suites' => ['email_notification' => ['enabled' => true, 'recipients' => ['sales@example.com', 'ops@example.com']]]],
    ]);

    app(SubmitForm::class)($form, ['email' => 'jane@example.com']);

    Queue::assertPushed(SendQueuedMail::class, 2);
    Queue::assertPushed(SendQueuedMail::class, fn (SendQueuedMail $job) => $job->templateKey === 'core.form_submission' && $job->to === 'sales@example.com');
    Queue::assertPushed(SendQueuedMail::class, fn (SendQueuedMail $job) => $job->templateKey === 'core.form_submission' && $job->to === 'ops@example.com');
    expect(MailLogEntry::where('template_key', 'core.form_submission')->count())->toBe(2);
});

it('never sends the notification e-mail when the step is disabled', function () {
    Queue::fake();

    $form = app(SaveForm::class)(null, [
        'slug' => 'contact',
        'title' => 'Contact',
        'fields' => [['key' => 'email', 'type' => 'email', 'required' => true]],
        'settings' => ['suites' => ['email_notification' => ['enabled' => false, 'recipients' => ['sales@example.com']]]],
    ]);

    app(SubmitForm::class)($form, ['email' => 'jane@example.com']);

    Queue::assertNotPushed(SendQueuedMail::class);
});

it('sends the acknowledgement to the submitter using the per-form subject/body (§8.3)', function () {
    Queue::fake();

    $form = app(SaveForm::class)(null, [
        'slug' => 'contact',
        'title' => 'Contact',
        'fields' => [['key' => 'email', 'type' => 'email', 'required' => true]],
        'settings' => ['suites' => ['acknowledgement' => [
            'enabled' => true,
            'subject' => 'Merci !',
            'body' => 'Nous avons bien reçu votre message pour {{ form_title }}.',
        ]]],
    ]);

    app(SubmitForm::class)($form, ['email' => 'jane@example.com']);

    Queue::assertPushed(SendQueuedMail::class, fn (SendQueuedMail $job) => $job->to === 'jane@example.com'
        && $job->subject === 'Merci !'
        && str_contains($job->html, 'Nous avons bien reçu votre message pour Contact.'));
});

it('never sends an acknowledgement without an e-mail field, even when enabled', function () {
    Queue::fake();

    $form = app(SaveForm::class)(null, [
        'slug' => 'survey',
        'title' => 'Survey',
        'fields' => [['key' => 'comment', 'type' => 'text']],
        'settings' => ['suites' => ['acknowledgement' => ['enabled' => true, 'subject' => 'Merci', 'body' => 'Reçu.']]],
    ]);

    app(SubmitForm::class)($form, ['comment' => 'Bonjour']);

    Queue::assertNotPushed(SendQueuedMail::class);
});

it('never sends an acknowledgement left enabled without subject/body content', function () {
    Queue::fake();

    $form = app(SaveForm::class)(null, [
        'slug' => 'contact',
        'title' => 'Contact',
        'fields' => [['key' => 'email', 'type' => 'email', 'required' => true]],
        'settings' => ['suites' => ['acknowledgement' => ['enabled' => true]]],
    ]);

    app(SubmitForm::class)($form, ['email' => 'jane@example.com']);

    Queue::assertNotPushed(SendQueuedMail::class);
});

it('notifies holders of forms.submissions_view, and no one else (§8.4)', function () {
    Notification::fake();
    Queue::fake();

    $form = app(SaveForm::class)(null, [
        'slug' => 'contact',
        'title' => 'Contact',
        'fields' => [],
        'settings' => ['suites' => ['admin_notification' => ['enabled' => true]]],
    ]);

    $reviewer = formsSuitesActor();
    app(GrantPermission::class)($reviewer, 'baobab.system.forms.submissions_view');
    $bystander = formsSuitesActor();

    app(SubmitForm::class)($form, []);

    Notification::assertSentTo($reviewer, BaobabNotification::class);
    Notification::assertNotSentTo($bystander, BaobabNotification::class);
});

it('dispatches the webhook only when the form enables it, even with an active subscription (§8.5)', function () {
    Queue::fake();

    WebhookSubscription::create([
        'url' => 'https://example.com/hook',
        'secret' => str_repeat('a', 32),
        'events' => ['baobab.form.submitted'],
        'is_active' => true,
    ]);

    $disabled = app(SaveForm::class)(null, [
        'slug' => 'disabled', 'title' => 'Disabled', 'fields' => [],
        'settings' => ['suites' => ['webhook' => ['enabled' => false]]],
    ]);
    app(SubmitForm::class)($disabled, []);
    Queue::assertNotPushed(DeliverWebhook::class);

    $enabled = app(SaveForm::class)(null, [
        'slug' => 'enabled', 'title' => 'Enabled', 'fields' => [],
        'settings' => ['suites' => ['webhook' => ['enabled' => true]]],
    ]);
    app(SubmitForm::class)($enabled, []);
    Queue::assertPushed(DeliverWebhook::class, 1);
});

it('never lets the webhook setting silence the other suites steps (suivi n° 278)', function () {
    Notification::fake();
    Queue::fake();

    $form = app(SaveForm::class)(null, [
        'slug' => 'contact',
        'title' => 'Contact',
        'fields' => [['key' => 'email', 'type' => 'email', 'required' => true]],
        'settings' => ['suites' => [
            'email_notification' => ['enabled' => true, 'recipients' => ['sales@example.com']],
            'admin_notification' => ['enabled' => true],
            'webhook' => ['enabled' => false],
        ]],
    ]);

    $reviewer = formsSuitesActor();
    app(GrantPermission::class)($reviewer, 'baobab.system.forms.submissions_view');

    app(SubmitForm::class)($form, ['email' => 'jane@example.com']);

    Queue::assertPushed(SendQueuedMail::class, fn (SendQueuedMail $job) => $job->to === 'sales@example.com');
    Notification::assertSentTo($reviewer, BaobabNotification::class);
    Queue::assertNotPushed(DeliverWebhook::class);
});

it('fires baobab.form.submitted as a free extension point, regardless of any suites setting', function () {
    $form = app(SaveForm::class)(null, ['slug' => 'contact', 'title' => 'Contact', 'fields' => []]);

    $receivedFormId = null;
    Hook::listen('baobab.form.submitted', function ($hookedForm, $submission) use (&$receivedFormId) {
        $receivedFormId = $hookedForm->id;
    });

    app(SubmitForm::class)($form, []);

    expect($receivedFormId)->toBe($form->id);
});

it('never triggers any suites step for a submission marked spam (suivi n° 278)', function () {
    Notification::fake();
    Queue::fake();

    WebhookSubscription::create([
        'url' => 'https://example.com/hook',
        'secret' => str_repeat('a', 32),
        'events' => ['baobab.form.submitted'],
        'is_active' => true,
    ]);

    $form = app(SaveForm::class)(null, [
        'slug' => 'contact',
        'title' => 'Contact',
        'fields' => [['key' => 'email', 'type' => 'email', 'required' => true]],
        'settings' => ['suites' => [
            'email_notification' => ['enabled' => true, 'recipients' => ['sales@example.com']],
            'admin_notification' => ['enabled' => true],
            'webhook' => ['enabled' => true],
        ]],
    ]);

    $reviewer = formsSuitesActor();
    app(GrantPermission::class)($reviewer, 'baobab.system.forms.submissions_view');

    $hookFired = false;
    Hook::listen('baobab.form.submitted', function ($hookedForm, $submission) use (&$hookFired) {
        $hookFired = true;
    });

    app(SubmitForm::class)($form, [
        'email' => 'jane@example.com',
        FormSpamGuard::HONEYPOT_FIELD => 'i am a bot',
    ]);

    expect($hookFired)->toBeFalse();
    Queue::assertNotPushed(SendQueuedMail::class);
    Queue::assertNotPushed(DeliverWebhook::class);
    Notification::assertNothingSent();
});
