<?php

use Baobab\Audit\Models\AuditEntry;
use Baobab\Mail\Actions\SaveMailTemplate;
use Baobab\Mail\Exceptions\InvalidMailTemplateException;
use Baobab\Mail\Exceptions\MailTemplateNotFoundException;
use Baobab\Mail\Jobs\SendQueuedMail;
use Baobab\Mail\Mailer;
use Baobab\Mail\Models\MailTemplateOverride;
use Baobab\Mail\TemplateRegistry;
use Illuminate\Support\Facades\Queue;

/**
 * Un template Core taillé pour ces tests : `core.test` n'a qu'une variable et
 * aucune `required`, il ne permettrait pas d'exercer la validation bloquante.
 * Le fichier de défauts est écrit sur disque parce que c'est bien un fichier
 * que `TemplateRegistry` lit — le simuler masquerait la moitié du chemin.
 *
 * @param  array<string, string|array{label: string, required?: bool}>  $variables
 */
function declareMailTemplate(array $variables, string $subject, string $body): string
{
    $path = sys_get_temp_dir().'/baobab-test-mail-'.getmypid().'.json';

    file_put_contents($path, json_encode(['subject' => $subject, 'body' => $body]));

    config(['baobab.mail.templates' => [[
        'key' => 'core.account_activation',
        'description' => 'Fixture de test.',
        'variables' => $variables,
        'defaults' => $path,
    ]]]);

    return 'core.account_activation';
}

it('stores a customisation and reports the template as customised', function () {
    $key = declareMailTemplate(
        ['user.name' => 'Nom', 'activation_url' => ['label' => 'Lien', 'required' => true]],
        'Activez votre compte {{ user.name }}',
        'Cliquez : {{ activation_url }}',
    );

    app(SaveMailTemplate::class)($key, [
        'subject' => 'À vous de jouer, {{ user.name }}',
        'body' => 'Votre lien : {{ activation_url }}',
    ]);

    $template = app(TemplateRegistry::class)->find($key);

    expect($template->subject)->toBe('À vous de jouer, {{ user.name }}')
        ->and($template->customised)->toBeTrue()
        ->and(app(TemplateRegistry::class)->customisedKeys())->toBe([$key]);
});

it('leaves the code default untouched and still reachable', function () {
    $key = declareMailTemplate(
        ['activation_url' => ['label' => 'Lien', 'required' => true]],
        'Sujet par défaut',
        'Corps par défaut : {{ activation_url }}',
    );

    app(SaveMailTemplate::class)($key, [
        'subject' => 'Sujet personnalisé',
        'body' => 'Corps personnalisé : {{ activation_url }}',
    ]);

    expect(app(TemplateRegistry::class)->default($key)->subject)->toBe('Sujet par défaut')
        ->and(app(TemplateRegistry::class)->default($key)->customised)->toBeFalse();
});

it('refuses a customisation that drops a required variable', function () {
    $key = declareMailTemplate(
        ['activation_url' => ['label' => 'Lien d\'activation', 'required' => true]],
        'Sujet',
        'Corps : {{ activation_url }}',
    );

    expect(fn () => app(SaveMailTemplate::class)($key, [
        'subject' => 'Sujet',
        'body' => 'Un corps sans le lien.',
    ]))->toThrow(InvalidMailTemplateException::class, 'activation_url');

    expect(MailTemplateOverride::query()->count())->toBe(0);
});

it('does not accept a required variable merely tested in a conditional block', function () {
    $key = declareMailTemplate(
        ['activation_url' => ['label' => 'Lien', 'required' => true]],
        'Sujet',
        'Corps : {{ activation_url }}',
    );

    expect(fn () => app(SaveMailTemplate::class)($key, [
        'subject' => 'Sujet',
        'body' => '{{# if activation_url }}Un lien existe.{{/ if }}',
    ]))->toThrow(InvalidMailTemplateException::class);
});

it('refuses a customisation using an undeclared variable', function () {
    $key = declareMailTemplate(['user.name' => 'Nom'], 'Sujet', 'Bonjour {{ user.name }}');

    expect(fn () => app(SaveMailTemplate::class)($key, [
        'subject' => 'Sujet',
        'body' => 'Bonjour {{ user.name }}, votre solde est {{ account.balance }}.',
    ]))->toThrow(InvalidMailTemplateException::class, 'account.balance');
});

it('refuses an undeclared variable even when it only appears in a conditional', function () {
    $key = declareMailTemplate(['user.name' => 'Nom'], 'Sujet', 'Bonjour {{ user.name }}');

    expect(fn () => app(SaveMailTemplate::class)($key, [
        'subject' => 'Sujet',
        'body' => '{{# if account.balance }}Solde disponible{{/ if }} {{ user.name }}',
    ]))->toThrow(InvalidMailTemplateException::class, 'account.balance');
});

it('refuses to customise a template no code declares', function () {
    expect(fn () => app(SaveMailTemplate::class)('ghost.template', [
        'subject' => 'Sujet',
        'body' => 'Corps',
    ]))->toThrow(MailTemplateNotFoundException::class);
});

it('audits the save', function () {
    $key = declareMailTemplate(['user.name' => 'Nom'], 'Sujet', 'Bonjour {{ user.name }}');

    app(SaveMailTemplate::class)($key, ['subject' => 'Sujet', 'body' => 'Salut {{ user.name }}']);

    expect(AuditEntry::where('action', 'mail.template_saved')->exists())->toBeTrue();
});

it('sends the customised subject and body, not the default', function () {
    Queue::fake();

    $key = declareMailTemplate(['user.name' => 'Nom'], 'Sujet par défaut', 'Corps par défaut');

    app(SaveMailTemplate::class)($key, [
        'subject' => 'Sujet personnalisé',
        'body' => 'Bonjour {{ user.name }}, corps personnalisé.',
    ]);

    app(Mailer::class)->send($key, 'dest@example.com', ['user' => ['name' => 'Awa']]);

    Queue::assertPushed(SendQueuedMail::class, fn (SendQueuedMail $job) => $job->subject === 'Sujet personnalisé'
        && str_contains($job->html, 'Bonjour Awa, corps personnalisé.'));
});

it('applies a per-template sender, and falls back to the global one without it', function () {
    Queue::fake();

    $key = declareMailTemplate(['user.name' => 'Nom'], 'Sujet', 'Bonjour {{ user.name }}');

    app(SaveMailTemplate::class)($key, [
        'subject' => 'Sujet',
        'body' => 'Bonjour {{ user.name }}',
        'from_address' => 'support@example.com',
        'from_name' => 'Support',
    ]);

    app(Mailer::class)->send($key, 'dest@example.com', ['user' => ['name' => 'Awa']]);

    Queue::assertPushed(SendQueuedMail::class, fn (SendQueuedMail $job) => $job->fromAddress === 'support@example.com'
        && $job->fromName === 'Support');

    app(SaveMailTemplate::class)($key, ['subject' => 'Sujet', 'body' => 'Bonjour {{ user.name }}']);

    app(Mailer::class)->send($key, 'autre@example.com', ['user' => ['name' => 'Awa']]);

    Queue::assertPushed(SendQueuedMail::class, fn (SendQueuedMail $job) => $job->to === 'autre@example.com'
        && $job->fromAddress === null);
});
