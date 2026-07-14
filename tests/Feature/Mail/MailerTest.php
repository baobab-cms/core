<?php

use Baobab\Facades\Hook;
use Baobab\Mail\Jobs\SendQueuedMail;
use Baobab\Mail\Mailer;
use Illuminate\Support\Facades\Queue;

it('dispatches a rendered, inlined e-mail on the baobab queue connection', function () {
    Queue::fake();

    app(Mailer::class)->send('core.test', 'dest@example.com', ['sent_at' => '14/07/2026 10:00']);

    Queue::assertPushedOn('baobab', SendQueuedMail::class, function (SendQueuedMail $job) {
        return $job->templateKey === 'core.test'
            && $job->to === 'dest@example.com'
            && str_contains($job->subject, 'test')
            && str_contains($job->html, '14/07/2026 10:00')
            && str_contains($job->html, 'style=') // le CSS du layout a bien été inliné
            && str_contains($job->text, '14/07/2026 10:00')
            && ! str_contains($job->text, '<');
    });
});

it('cancels the send when the baobab.mail.sending filter returns null', function () {
    Queue::fake();

    Hook::modify('baobab.mail.sending', fn () => null, priority: 5);

    app(Mailer::class)->send('core.test', 'dest@example.com', ['sent_at' => 'now']);

    Queue::assertNotPushed(SendQueuedMail::class);
});

it('lets the baobab.mail.sending filter rewrite the recipient', function () {
    Queue::fake();

    Hook::modify('baobab.mail.sending', function (array $payload) {
        $payload['to'] = 'rerouted@example.com';

        return $payload;
    }, priority: 5);

    app(Mailer::class)->send('core.test', 'dest@example.com', ['sent_at' => 'now']);

    Queue::assertPushedOn('baobab', SendQueuedMail::class, fn (SendQueuedMail $job) => $job->to === 'rerouted@example.com');
});
