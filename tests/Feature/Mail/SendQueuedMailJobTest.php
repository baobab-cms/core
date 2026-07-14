<?php

use Baobab\Facades\Hook;
use Baobab\Mail\Jobs\SendQueuedMail;
use Baobab\Mail\Mailables\RenderedMail;
use Illuminate\Support\Facades\Mail;

it('sends the rendered mailable and fires baobab.mail.sent', function () {
    Mail::fake();

    $captured = null;
    Hook::listen('baobab.mail.sent', function (string $key, string $to) use (&$captured): void {
        $captured = [$key, $to];
    });

    $job = new SendQueuedMail('core.test', 'dest@example.com', 'Sujet', '<p>Corps</p>', 'Corps');
    $job->handle();

    Mail::assertSent(RenderedMail::class, function (RenderedMail $mail) {
        return $mail->hasTo('dest@example.com');
    });

    expect($captured)->toBe(['core.test', 'dest@example.com']);
});

it('fires baobab.mail.failed via the native failed() hook, without swallowing retries', function () {
    $captured = null;
    Hook::listen('baobab.mail.failed', function (string $key, string $to) use (&$captured): void {
        $captured = [$key, $to];
    });

    $job = new SendQueuedMail('core.test', 'dest@example.com', 'Sujet', '<p>Corps</p>', 'Corps');
    $job->failed(new RuntimeException('SMTP indisponible'));

    expect($captured)->toBe(['core.test', 'dest@example.com'])
        ->and($job->tries)->toBe(3)
        ->and($job->backoff())->toBe([10, 30, 60]);
});
