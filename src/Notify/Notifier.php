<?php

declare(strict_types=1);

namespace Baobab\Notify;

use Baobab\Facades\Hook;
use Baobab\Mail\Mailer;
use Baobab\Notify\Models\NotificationPreference;
use Baobab\Notify\Notifications\BaobabNotification;
use Baobab\Support\Logger;
use Baobab\Users\Models\User;

/**
 * Point d'entrée unique pour toute notification applicative (spec 11 §5.2) —
 * l'équivalent notification de `Baobab\Mail\Mailer` : résout la déclaration,
 * applique les préférences du destinataire, dispatch sur les canaux
 * restants. Canal `database` via le système de notifications Laravel natif
 * (`BaobabNotification`, mis en queue) ; canal `mail` en appelant directement
 * `Baobab\Mail\Mailer::send()` (jamais le channel `mail` natif).
 */
final class Notifier
{
    public function __construct(
        private readonly NotificationRegistry $registry,
        private readonly Mailer $mailer,
        private readonly Logger $logger,
    ) {}

    /**
     * @param  iterable<User>  $recipients
     * @param  array<string, mixed>  $data
     */
    public function send(string $key, iterable $recipients, array $data = []): void
    {
        $declaration = $this->registry->find($key);

        foreach ($recipients as $recipient) {
            $channels = $this->resolveChannels($declaration, $recipient);

            if ($channels === []) {
                continue;
            }

            /** @var array{recipient: User, channels: list<string>, data: array<string, mixed>}|null $payload */
            $payload = Hook::filter('baobab.notification.sending', [
                'recipient' => $recipient,
                'channels' => $channels,
                'data' => $data,
            ], $key);

            if ($payload === null) {
                $this->logger->info('Notification annulée par un filtre baobab.notification.sending.', [
                    'key' => $key,
                    'recipient_id' => $recipient->getKey(),
                ]);

                continue;
            }

            if (in_array('database', $payload['channels'], true)) {
                $payload['recipient']->notify(
                    (new BaobabNotification($key, $declaration->description, $payload['data']))
                        ->onConnection('baobab')
                        ->onQueue('baobab'),
                );
            }

            if (in_array('mail', $payload['channels'], true) && $declaration->mailTemplate !== null) {
                $this->mailer->send($declaration->mailTemplate, $payload['recipient'], $payload['data']);
            }

            Hook::action('baobab.notification.sent', $key, $payload['recipient']);
        }
    }

    /**
     * @return list<string>
     */
    private function resolveChannels(NotificationDeclaration $declaration, User $recipient): array
    {
        if (! $declaration->configurable) {
            return $declaration->channels;
        }

        $disabled = NotificationPreference::query()
            ->where('user_id', $recipient->getKey())
            ->where('key', $declaration->key)
            ->where('enabled', false)
            ->pluck('channel')
            ->all();

        return array_values(array_diff($declaration->channels, $disabled));
    }
}
