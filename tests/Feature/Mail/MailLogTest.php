<?php

use Baobab\Facades\Hook;
use Baobab\Mail\Jobs\SendQueuedMail;
use Baobab\Mail\Mailer;
use Baobab\Mail\MailLogStatus;
use Baobab\Mail\Models\MailLogEntry;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

it('records a queued entry when an e-mail is dispatched', function () {
    Queue::fake();

    app(Mailer::class)->send('core.test', 'dest@example.com', ['sent_at' => '14/07/2026 10:00']);

    $entry = MailLogEntry::sole();

    expect($entry->template_key)->toBe('core.test')
        ->and($entry->recipient)->toBe('dest@example.com')
        ->and($entry->status)->toBe(MailLogStatus::Queued)
        ->and($entry->sent_at)->toBeNull()
        ->and($entry->subject)->not->toBe('');
});

it('carries the log entry id into the job so the job can close the loop', function () {
    Queue::fake();

    app(Mailer::class)->send('core.test', 'dest@example.com', ['sent_at' => 'now']);

    $entry = MailLogEntry::sole();

    Queue::assertPushed(SendQueuedMail::class, fn (SendQueuedMail $job) => $job->mailLogId === $entry->id);
});

it('does not store the rendered body by default', function () {
    Queue::fake();

    app(Mailer::class)->send('core.test', 'dest@example.com', ['sent_at' => 'now']);

    expect(MailLogEntry::sole()->body)->toBeNull();
});

it('stores the rendered body only when log_body is enabled', function () {
    Queue::fake();
    config(['baobab.mail.log_body' => true]);

    app(Mailer::class)->send('core.test', 'dest@example.com', ['sent_at' => '14/07/2026 10:00']);

    expect(MailLogEntry::sole()->body)->toContain('14/07/2026 10:00');
});

it('records nothing when the sending filter cancels the e-mail', function () {
    Queue::fake();

    Hook::modify('baobab.mail.sending', fn () => null, priority: 5);

    app(Mailer::class)->send('core.test', 'dest@example.com', ['sent_at' => 'now']);

    expect(MailLogEntry::count())->toBe(0);
});

it('marks the entry sent, with the transport actually used', function () {
    Mail::fake();
    config(['mail.default' => 'array']);

    $entry = MailLogEntry::create([
        'template_key' => 'core.test',
        'recipient' => 'dest@example.com',
        'subject' => 'Sujet',
        'status' => MailLogStatus::Queued,
    ]);

    (new SendQueuedMail('core.test', 'dest@example.com', 'Sujet', '<p>Corps</p>', 'Corps', null, null, $entry->id))->handle();

    $entry->refresh();

    expect($entry->status)->toBe(MailLogStatus::Sent)
        ->and($entry->mailer)->toBe('array')
        ->and($entry->sent_at)->not->toBeNull();
});

it('marks the entry failed and keeps the reason, once the retries are exhausted', function () {
    $entry = MailLogEntry::create([
        'template_key' => 'core.test',
        'recipient' => 'dest@example.com',
        'subject' => 'Sujet',
        'status' => MailLogStatus::Queued,
    ]);

    (new SendQueuedMail('core.test', 'dest@example.com', 'Sujet', '<p>Corps</p>', 'Corps', null, null, $entry->id))
        ->failed(new RuntimeException('SMTP indisponible'));

    $entry->refresh();

    expect($entry->status)->toBe(MailLogStatus::Failed)
        ->and($entry->error)->toContain('SMTP indisponible')
        ->and($entry->sent_at)->toBeNull();
});

it('truncates a very long failure reason instead of refusing to record it', function () {
    $entry = MailLogEntry::create([
        'template_key' => 'core.test',
        'recipient' => 'dest@example.com',
        'subject' => 'Sujet',
        'status' => MailLogStatus::Queued,
    ]);

    (new SendQueuedMail('core.test', 'dest@example.com', 'Sujet', '<p>Corps</p>', 'Corps', null, null, $entry->id))
        ->failed(new RuntimeException(str_repeat('x', 5_000)));

    expect(mb_strlen((string) $entry->refresh()->error))->toBeLessThanOrEqual(1_010);
});

/**
 * Un job sérialisé **avant** cette passe ne porte pas d'identifiant de
 * journal : il doit s'envoyer quand même, sans quoi la montée de version
 * ferait échouer tout ce qui attendait en file au moment du déploiement.
 */
it('still sends a job that carries no log entry id', function () {
    Mail::fake();

    (new SendQueuedMail('core.test', 'dest@example.com', 'Sujet', '<p>Corps</p>', 'Corps'))->handle();

    expect(MailLogEntry::count())->toBe(0);
    Mail::assertSentCount(1);
});
