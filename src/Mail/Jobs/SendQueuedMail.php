<?php

declare(strict_types=1);

namespace Baobab\Mail\Jobs;

use Baobab\Facades\Hook;
use Baobab\Mail\Mailables\RenderedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
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

        Hook::action('baobab.mail.sent', $this->templateKey, $this->to);
    }

    public function failed(Throwable $exception): void
    {
        Hook::action('baobab.mail.failed', $this->templateKey, $this->to, $exception);
    }
}
