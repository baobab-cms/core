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

        $this->dispatch(
            $key,
            $to,
            $this->renderer->render($template->subject, $data),
            $this->renderer->render($template->body, $data),
            $data,
            $template->fromAddress,
            $template->fromName,
        );
    }

    /**
     * Un e-mail dont le sujet/corps ne vient pas d'un template déclaré au
     * registre (§3.1) mais d'un contenu propre à une instance — l'accusé de
     * réception d'un formulaire (spec 14 §8.3, « template dédié par
     * formulaire »), configuré par formulaire plutôt que globalement par
     * l'admin. Même rendu, même journal, même queue que `send()` — seule la
     * source du sujet/corps change, d'où l'extraction dans `dispatch()`
     * plutôt qu'une duplication. `$logKey` n'a pas besoin de correspondre à
     * un template déclaré : `TemplateRegistry::declaration()` peut ne pas le
     * connaître, le journal le rend simplement non-renvoyable (patron déjà
     * suivi pour un template disparu avec son module).
     *
     * @param  array<string, mixed>  $data
     */
    public function sendCustom(string $logKey, string $recipient, string $subject, string $body, array $data = []): void
    {
        $this->dispatch(
            $logKey,
            $recipient,
            $this->renderer->render($subject, $data),
            $this->renderer->render($body, $data),
            $data,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function dispatch(string $logKey, string $to, string $subject, string $body, array $data, ?string $fromAddress = null, ?string $fromName = null): void
    {
        /** @var array{to: string, subject: string, data: array<string, mixed>}|null $payload */
        $payload = Hook::filter('baobab.mail.sending', ['to' => $to, 'subject' => $subject, 'data' => $data], $logKey);

        if ($payload === null) {
            $this->logger->info('Envoi d\'e-mail annulé par un filtre baobab.mail.sending.', ['key' => $logKey, 'to' => $to]);

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
            'template_key' => $logKey,
            'recipient' => $payload['to'],
            'subject' => $payload['subject'],
            'status' => MailLogStatus::Queued,
            'body' => config('baobab.mail.log_body') === true ? $html : null,
        ]);

        SendQueuedMail::dispatch(
            $logKey,
            $payload['to'],
            $payload['subject'],
            $html,
            $this->toPlainText($body),
            $fromAddress,
            $fromName,
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
        $body = $this->keepLinkTargets($body);

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

    /**
     * `strip_tags()` retire `<a href="…">libellé</a>` tout entier avec sa
     * cible : la partie texte gardait « Annuler l'effacement » sans aucune
     * adresse, si bien que le lien d'un e-mail (confirmer, annuler, récupérer
     * une archive, réinitialiser un mot de passe) n'existait plus pour qui lit
     * le texte — client texte seul, ou journal. Défaut du M5 trouvé le
     * 21 septembre 2026 en vérifiant l'annulation d'un effacement du portail
     * RGPD ; même famille que les deux corrections précédentes de cette
     * fonction, et le troisième défaut n'avait pas été vu parce qu'on
     * regardait le texte, pas les liens.
     *
     * La cible est reprise **encore encodée** : l'unique décodage d'entités
     * qui suit la rétablit, alors qu'un premier décodage ici ferait lire
     * `&copy=2` d'une adresse comme une entité au second.
     */
    private function keepLinkTargets(string $body): string
    {
        $replaced = preg_replace_callback(
            '#<a\s[^>]*?href\s*=\s*(["\'])(.*?)\1[^>]*>(.*?)</a>#is',
            function (array $match): string {
                $target = trim($match[2]);
                $label = trim(strip_tags($match[3]));

                if ($target === '' || str_starts_with($target, '#')) {
                    return $match[3];
                }

                // Un lien dont le libellé est déjà son adresse ne se répète pas.
                if ($label === '' || html_entity_decode($label, ENT_QUOTES | ENT_HTML5, 'UTF-8') === html_entity_decode($target, ENT_QUOTES | ENT_HTML5, 'UTF-8')) {
                    return $target;
                }

                return "{$label} ({$target})";
            },
            $body,
        );

        return $replaced ?? $body;
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
