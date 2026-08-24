<?php

use Baobab\Facades\Hook;
use Baobab\Mail\Actions\SaveMailTemplate;
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

/**
 * `strip_tags()` ne met rien à la place de ce qu'il retire : deux paragraphes
 * se recollaient mot contre mot dans la partie texte. Défaut du M5 trouvé le
 * 24 août 2026 en vérification navigateur, dans un vrai e-mail — la version
 * HTML, elle, était juste (suivi n° 198).
 */
it('keeps block boundaries in the plain-text alternative', function () {
    Queue::fake();

    app(SaveMailTemplate::class)('core.test', [
        'subject' => 'Test',
        'body' => '<p>Envoyé le {{ sent_at }}.</p><p>Second paragraphe.</p><ul><li>Un</li><li>Deux</li></ul>',
    ]);

    app(Mailer::class)->send('core.test', 'dest@example.com', ['sent_at' => '14/07/2026 10:00']);

    Queue::assertPushed(SendQueuedMail::class, function (SendQueuedMail $job) {
        return str_contains($job->text, "10:00.\n\nSecond paragraphe.")
            && str_contains($job->text, "Un\nDeux")
            && ! str_contains($job->text, '10:00.Second');
    });
});

it('does not leave blank-line holes where the HTML was merely indented', function () {
    Queue::fake();

    app(SaveMailTemplate::class)('core.test', [
        'subject' => 'Test',
        'body' => "<div>\n    <p>Envoyé le {{ sent_at }}.</p>\n\n    <p>Fin.</p>\n</div>",
    ]);

    app(Mailer::class)->send('core.test', 'dest@example.com', ['sent_at' => 'ce matin']);

    Queue::assertPushed(SendQueuedMail::class, fn (SendQueuedMail $job) => ! str_contains($job->text, "\n\n\n"));
});
