<?php

declare(strict_types=1);

namespace Baobab\Notify\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Enveloppe Laravel pour le canal `database` uniquement (spec 11 §5.1) — le
 * canal `mail` d'une notification Baobab ne passe jamais par le channel
 * `mail` natif de Laravel, `Baobab\Notify\Notifier` appelle directement
 * `Baobab\Mail\Mailer::send()`. Cette classe ne porte donc que `via()` =
 * `['database']`.
 */
final class BaobabNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        private readonly string $key,
        private readonly ?string $description,
        private readonly array $data,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'key' => $this->key,
            'description' => $this->description,
            'data' => $this->data,
        ];
    }
}
