<?php

declare(strict_types=1);

namespace Baobab\Mail\Jobs;

use Baobab\Facades\Hook;
use Baobab\Mail\Mailables\RenderedMail;
use Baobab\Mail\MailLogStatus;
use Baobab\Mail\Models\MailLogEntry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Envoi effectif d'un e-mail déjà résolu/rendu (spec 13 §2.2) — jamais
 * synchrone dans la requête HTTP. 3 tentatives, backoff progressif ; l'échec
 * final est géré par le mécanisme natif de la queue (`failed()`), jamais
 * avalé par un try/catch qui casserait les retries.
 */
final class SendQueuedMail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly string $templateKey,
        public readonly string $to,
        public readonly string $subject,
        public readonly string $html,
        public readonly string $text,
        public readonly ?string $fromAddress = null,
        public readonly ?string $fromName = null,
        public readonly ?int $mailLogId = null,
    ) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function handle(): void
    {
        Mail::to($this->to)->send(new RenderedMail(
            $this->subject,
            $this->html,
            $this->text,
            $this->fromAddress,
            $this->fromName,
        ));

        // Le transport est relevé **ici** et non au dispatch : entre la mise
        // en file et l'envoi effectif, `mail.default` a pu changer, et une
        // valeur figée trop tôt ferait mentir la colonne au moment précis où
        // on la consulte — un diagnostic d'envoi (§4.1).
        $this->markLog(MailLogStatus::Sent, ['sent_at' => now(), 'mailer' => config('mail.default')]);

        Hook::action('baobab.mail.sent', $this->templateKey, $this->to);
    }

    public function failed(Throwable $exception): void
    {
        // `failed()` ne se déclenche qu'après épuisement des 3 tentatives :
        // la ligne reste donc `queued` pendant les retries, ce qui est exact
        // — l'e-mail est bien encore en cours d'acheminement.
        $this->markLog(MailLogStatus::Failed, ['error' => Str::limit($exception->getMessage(), 1000)]);

        Hook::action('baobab.mail.failed', $this->templateKey, $this->to, $exception);
    }

    /**
     * L'absence de ligne n'est pas une erreur : un job sérialisé **avant** la
     * Pass B1 n'en porte pas (`mailLogId` nullable en fin de constructeur pour
     * cette raison précise), et la purge de rétention peut avoir emporté la
     * ligne d'un job resté longtemps en échec. Dans les deux cas l'envoi doit
     * aboutir quand même.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function markLog(MailLogStatus $status, array $attributes = []): void
    {
        if ($this->mailLogId === null) {
            return;
        }

        MailLogEntry::query()
            ->whereKey($this->mailLogId)
            ->update(['status' => $status] + $attributes);
    }
}
