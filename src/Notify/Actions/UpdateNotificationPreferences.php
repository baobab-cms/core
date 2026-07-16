<?php

declare(strict_types=1);

namespace Baobab\Notify\Actions;

use Baobab\Audit\AuditLogger;
use Baobab\Facades\Hook;
use Baobab\Notify\Models\NotificationPreference;
use Baobab\Users\Models\User;

/**
 * Met à jour les préférences de notification d'un utilisateur (spec 11 §7) —
 * scope `own` implicite, un administrateur ne modifie jamais les préférences
 * d'autrui (vérifié par l'appelant, cette Action ne fait confiance qu'au
 * `$user` fourni). Une ligne n'existe que pour un canal désactivé (l'absence
 * de ligne signifie « actif par défaut ») — repasser à `true` supprime la
 * ligne plutôt que de la garder redondante.
 */
final class UpdateNotificationPreferences
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<string, array<string, bool>>  $matrix  clé de notification => [canal => activé]
     */
    public function __invoke(User $user, array $matrix): void
    {
        foreach ($matrix as $key => $channels) {
            foreach ($channels as $channel => $enabled) {
                if ($enabled) {
                    NotificationPreference::where('user_id', $user->id)
                        ->where('key', $key)
                        ->where('channel', $channel)
                        ->delete();

                    continue;
                }

                NotificationPreference::updateOrCreate(
                    ['user_id' => $user->id, 'key' => $key, 'channel' => $channel],
                    ['enabled' => false],
                );
            }
        }

        $this->audit->record('notification.preferences.updated', $user, ['matrix' => $matrix]);

        Hook::action('baobab.notification.preferences.updated', $user);
    }
}
