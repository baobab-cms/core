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

/**
 * Le template est déclaré **sans** resolver de façon explicite : depuis que
 * `core.test` en porte un (n° 200), s'en remettre à la configuration livrée
 * ferait dire à ce test l'inverse de ce qu'il vérifie.
 */
it('refuses to resend a template that declares no resolver', function () {
    Queue::fake();
    config(['baobab.mail.templates' => [[
        'key' => 'core.test',
        'description' => 'E-mail de test.',
        'variables' => ['sent_at' => 'Date/heure d\'envoi du test'],
        'defaults' => realpath(__DIR__.'/../../../resources/mails/core/test.json'),
    ]]]);

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

/**
 * Le Core consomme son propre contrat plutôt que de l'offrir sans l'employer :
 * `core.test` est renvoyable **tel que livré**, sans configuration de test.
 * Ajouté le 24 août 2026 après vérification navigateur — la B2 partait avec
 * un renvoi qu'aucun template du produit ne permettait d'exercer (n° 200).
 */
it('ships core.test resendable out of the box', function () {
    Queue::fake();

    $entry = MailLogEntry::create([
        'template_key' => 'core.test',
        'recipient' => 'dest@example.com',
        'subject' => 'Baobab CMS — e-mail de test',
        'status' => MailLogStatus::Failed,
    ]);

    app(ResendMail::class)($entry);

    Queue::assertPushed(SendQueuedMail::class);
});

/**
 * L'heure rendue est celle du **renvoi**, pas celle de l'envoi d'origine :
 * le §4.2 re-rend depuis l'état courant, et un test qui annoncerait une heure
 * vieille de trois jours mentirait sur ce qu'il vient de prouver.
 */
it('re-renders the test e-mail with the time of the resend', function () {
    Queue::fake();

    $entry = MailLogEntry::create([
        'template_key' => 'core.test',
        'recipient' => 'dest@example.com',
        'subject' => 'Baobab CMS — e-mail de test',
        'status' => MailLogStatus::Sent,
        'created_at' => now()->subDays(3),
    ]);

    app(ResendMail::class)($entry);

    Queue::assertPushed(SendQueuedMail::class, function (SendQueuedMail $job) {
        return str_contains($job->html, now()->format('d/m/Y'))
            && ! str_contains($job->html, now()->subDays(3)->format('d/m/Y'));
    });
});
