<?php

declare(strict_types=1);

namespace Baobab\Mail;

use Baobab\Branding\Models\BrandingSetting;
use Baobab\Facades\Hook;
use Baobab\Mail\Jobs\SendQueuedMail;
use Baobab\Mail\Support\PlaceholderRenderer;
use Baobab\Support\Logger;
use Baobab\Users\Models\User;

/**
 * Point d'entrée unique pour tout e-mail applicatif (spec 13 §2.3) — l'équivalent
 * e-mail de la couche d'actions : les modules n'utilisent jamais `Mail::send()`
 * ni un Mailable brut directement. Résout le template, rend sujet/corps,
 * injecte le layout Core + inline le CSS, puis dispatch en queue — jamais en
 * synchrone dans la requête HTTP (spec 13 §2.2).
 */
final class Mailer
{
    public function __construct(
        private readonly TemplateRegistry $templates,
        private readonly PlaceholderRenderer $renderer,
        private readonly CssInliner $inliner,
        private readonly Logger $logger,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function send(string $key, User|string $recipient, array $data = []): void
    {
        $template = $this->templates->find($key);
        $to = $recipient instanceof User ? $recipient->email : $recipient;

        $subject = $this->renderer->render($template->subject, $data);
        $body = $this->renderer->render($template->body, $data);

        /** @var array{to: string, subject: string, data: array<string, mixed>}|null $payload */
        $payload = Hook::filter('baobab.mail.sending', ['to' => $to, 'subject' => $subject, 'data' => $data], $key);

        if ($payload === null) {
            $this->logger->info('Envoi d\'e-mail annulé par un filtre baobab.mail.sending.', ['key' => $key, 'to' => $to]);

            return;
        }

        $branding = BrandingSetting::current()->load('logo');

        $html = $this->inliner->inline(view('baobab::emails.layout', [
            'subject' => $payload['subject'],
            'body' => $body,
            'branding' => $branding,
        ])->render());

        $text = trim(strip_tags($body));

        SendQueuedMail::dispatch(
            $key,
            $payload['to'],
            $payload['subject'],
            $html,
            $text,
            $template->fromAddress,
            $template->fromName,
        )
            ->onConnection('baobab')
            ->onQueue('baobab');
    }
}
