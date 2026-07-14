<?php

declare(strict_types=1);

namespace Baobab\ContentTypes\Actions;

use Baobab\ContentTypes\Editorial\Models\ContentLock;
use Baobab\Users\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Prend la main sur un contenu déjà verrouillé par un autre utilisateur
 * (spec 09 §7, réservée à `update_any` — vérifié par l'appelant). Le travail
 * en cours de l'utilisateur évincé reste préservé en autosave ; il n'est pas
 * notifié via une notification persistée (M5 point 4 n'existe pas encore) —
 * son prochain heartbeat rapporte simplement le nouveau détenteur, à charge
 * du client d'en informer visuellement.
 */
final class TakeOverContentLock
{
    public function __invoke(Model $entry, User $actor): ContentLock
    {
        return ContentLock::updateOrCreate(
            ['lockable_type' => $entry->getMorphClass(), 'lockable_id' => $entry->getKey()],
            ['user_id' => $actor->getKey()],
        );
    }
}
