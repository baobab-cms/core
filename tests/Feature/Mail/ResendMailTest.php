<?php

use Baobab\Audit\Models\AuditEntry;
use Baobab\Mail\Actions\ResendMail;
use Baobab\Mail\Contracts\MailDataResolver;
use Baobab\Mail\Exceptions\MailNotResendableException;
use Baobab\Mail\Jobs\SendQueuedMail;
use Baobab\Mail\MailLogStatus;
use Baobab\Mail\Models\MailLogEntry;
use Illuminate\Support\Facades\Queue;

/**
 * Le resolver ne reçoit que ce que le journal conserve — ici le destinataire
 * suffit, ce qui est exactement le périmètre retenu pour le renvoi en v1
 * (suivi n° 195).
 */
final class InviteResolver implements MailDataResolver
{
    public function resolve(MailLogEntry $entry): array
    {
        return ['sent_at' => 'recalculé pour '.$entry->recipient];
    }
}

final class VanishedSourceResolver implements MailDataResolver
{
    public function resolve(MailLogEntry $entry): ?array
    {
        return null;
    }
}

function resendableTemplate(string $resolver): void
{
    config(['baobab.mail.templates' => [
        [
            'key' => 'core.test',
            'description' => 'E-mail de test.',
            'variables' => ['sent_at' => 'Date/heure d\'envoi du test'],
            'defaults' => realpath(__DIR__.'/../../../resources/mails/core/test.json'),
            'resolver' => $resolver,
        ],
    ]]);
}

function loggedEntry(): MailLogEntry
{
    return MailLogEntry::create([
        'template_key' => 'core.test',
        'recipient' => 'dest@example.com',
        'subject' => 'Sujet',
        'status' => MailLogStatus::Failed,
        'error' => 'SMTP indisponible',
    ]);
}

it('re-renders from current state and dispatches a fresh e-mail', function () {
    Queue::fake();
    resendableTemplate(InviteResolver::class);

    app(ResendMail::class)(loggedEntry());

    Queue::assertPushed(SendQueuedMail::class, fn (SendQueuedMail $job) => str_contains($job->html, 'recalculé pour dest@example.com'));
});

/**
 * Le renvoi produit **sa propre** ligne : deux tentatives sont deux faits, et
 * recycler celle qu'on consultait effacerait l'échec qui a motivé le renvoi.
 */
it('records a new queued entry without touching the one being resent', function () {
    Queue::fake();
    resendableTemplate(InviteResolver::class);

    $original = loggedEntry();

    app(ResendMail::class)($original);

    expect(MailLogEntry::count())->toBe(2)
        ->and($original->refresh()->status)->toBe(MailLogStatus::Failed)
        ->and($original->error)->toBe('SMTP indisponible');
});

it('refuses to resend a template that declares no resolver', function () {
    Queue::fake();

    app(ResendMail::class)(loggedEntry());
})->throws(MailNotResendableException::class, 'ne déclare pas de resolver');

it('refuses to resend when the source no longer exists', function () {
    Queue::fake();
    resendableTemplate(VanishedSourceResolver::class);

    app(ResendMail::class)(loggedEntry());
})->throws(MailNotResendableException::class, 'plus être reconstituées');

it('sends nothing at all when the resolver gives up', function () {
    Queue::fake();
    resendableTemplate(VanishedSourceResolver::class);

    try {
        app(ResendMail::class)(loggedEntry());
    } catch (MailNotResendableException) {
        // attendu
    }

    Queue::assertNothingPushed();
    expect(MailLogEntry::count())->toBe(1);
});

it('audits the resend', function () {
    Queue::fake();
    resendableTemplate(InviteResolver::class);

    $entry = loggedEntry();

    app(ResendMail::class)($entry);

    expect(AuditEntry::where('action', 'mail.resent')->where('data->recipient', 'dest@example.com')->exists())->toBeTrue();
});
