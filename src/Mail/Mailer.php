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

        SendQueuedMail::dispatch(
            $key,
            $payload['to'],
            $payload['subject'],
            $this->wrap($payload['subject'], $body),
            $this->toPlainText($body),
            $template->fromAddress,
            $template->fromName,
        )
            ->onConnection('baobab')
            ->onQueue('baobab');
    }

    /**
     * Le HTML complet d'un template, tel qu'il partirait — layout Core monté,
     * CSS inliné, placeholders résolus. C'est **exactement** ce que `send()`
     * met dans le job (les deux passent par `wrap()`), ce qui est tout
     * l'intérêt : un aperçu qui se rendrait de son côté finirait par mentir
     * le jour où le layout ou l'inlining changent.
     *
     * Ne dispatche rien et n'émet aucun hook : `baobab.mail.sending` est un
     * filtre d'*envoi*, le déclencher pour afficher une page serait faux.
     *
     * @param  array<string, mixed>  $data
     */
    public function render(MailTemplate $template, array $data = []): string
    {
        return $this->wrap(
            $this->renderer->render($template->subject, $data),
            $this->renderer->render($template->body, $data),
        );
    }

    /**
     * La version texte de l'e-mail (multipart, spec 13 §3.4). `strip_tags()`
     * seul ne suffit pas : `PlaceholderRenderer` échappe les valeurs pour le
     * HTML, si bien qu'une apostrophe arrivait telle quelle dans la partie
     * `text/plain` (« Date/heure d&#039;envoi »). Défaut présent depuis le M5,
     * repéré dans un vrai e-mail le 22 août 2026 — les entités sont décodées
     * après le retrait des balises, jamais avant, sous peine de faire
     * réapparaître comme balise un `&lt;script&gt;` légitimement échappé.
     */
    private function toPlainText(string $body): string
    {
        return trim(html_entity_decode(strip_tags($body), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private function wrap(string $subject, string $body): string
    {
        return $this->inliner->inline(view('baobab::emails.layout', [
            'subject' => $subject,
            'body' => $body,
            'branding' => BrandingSetting::current()->load('logo'),
        ])->render());
    }
}
