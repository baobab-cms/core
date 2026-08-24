<?php

declare(strict_types=1);

namespace Baobab\Mail;

use Baobab\Branding\Models\BrandingSetting;
use Baobab\Facades\Hook;
use Baobab\Mail\Jobs\SendQueuedMail;
use Baobab\Mail\Models\MailLogEntry;
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

        $html = $this->wrap($payload['subject'], $body);

        // Le journal s'écrit **avant** le dispatch, et depuis le Core : une
        // ligne `queued` qui n'aurait pas de job serait un faux positif
        // visible, là qu'un job sans ligne serait un envoi invisible. Et pas
        // en écoutant `baobab.mail.sent`/`baobab.mail.failed` : ces hooks sont
        // des points d'extension **publics**, pas la plomberie du noyau — un
        // module qui en désenregistrerait un ne doit pas pouvoir aveugler le
        // journal.
        $entry = MailLogEntry::create([
            'template_key' => $key,
            'recipient' => $payload['to'],
            'subject' => $payload['subject'],
            'status' => MailLogStatus::Queued,
            'body' => config('baobab.mail.log_body') === true ? $html : null,
        ]);

        SendQueuedMail::dispatch(
            $key,
            $payload['to'],
            $payload['subject'],
            $html,
            $this->toPlainText($body),
            $template->fromAddress,
            $template->fromName,
            $entry->id,
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
        // Les frontières de bloc deviennent des sauts de ligne **avant** le
        // retrait des balises. `strip_tags()` ne met rien à la place de ce
        // qu'il enlève : deux paragraphes se recollaient mot contre mot
        // (« ...le 14/07/2026 10:00.14/07/2026 10:00 »), et la partie texte
        // de tout e-mail à plus d'un paragraphe était illisible. Défaut
        // présent depuis le M5, trouvé le 24 août 2026 dans un vrai e-mail —
        // même fonction que celle corrigée deux jours plus tôt pour les
        // entités, et le second défaut n'avait pas été vu parce qu'on
        // regardait les apostrophes.
        // Deux familles, et la distinction se voit à la lecture : un élément
        // de liste ou une ligne de tableau suit le précédent, un paragraphe
        // s'en détache. Tout mettre à la ligne vide ferait d'une liste de
        // trois items un texte de trois paragraphes.
        $spaced = preg_replace(
            ['#<br\s*/?>#i', '#</(?:li|tr)\s*>#i', '#</(?:p|div|h[1-6]|blockquote|section|article|ul|ol|table)\s*>#i'],
            ["\n", "\n", "\n\n"],
            $body,
        ) ?? $body;

        $text = html_entity_decode(strip_tags($spaced), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Un HTML indenté laisse des espaces en bord de ligne : les retirer
        // d'abord, sinon une ligne « vide » qui contient deux espaces survit
        // à la réduction qui suit.
        $text = (string) preg_replace("/[ \t]*\n[ \t]*/", "\n", $text);

        // Une ligne vide sépare deux paragraphes ; trois en trouent un.
        return trim((string) preg_replace("/\n{3,}/", "\n\n", $text));
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
