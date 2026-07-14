<?php

declare(strict_types=1);

namespace Baobab\ContentTypes\Actions;

use Baobab\ContentTypes\Editorial\Models\ContentLock;
use Baobab\ContentTypes\Exceptions\ContentLockedException;
use Baobab\Users\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Acquiert un verrou libre/expiré, ou l'entretient (heartbeat) s'il
 * appartient déjà à l'appelant (spec 09 §7) — une seule Action pour les deux
 * cas, appelée à l'ouverture du formulaire et à chaque battement de cœur.
 * Lève ContentLockedException si un autre utilisateur détient un verrou
 * encore valide — l'appelant décide alors d'afficher lecture seule ou de
 * proposer TakeOverContentLock (réservé à `update_any`).
 */
final class AcquireOrRefreshContentLock
{
    public function __invoke(Model $entry, User $actor): ContentLock
    {
        $lock = ContentLock::where('lockable_type', $entry->getMorphClass())
            ->where('lockable_id', $entry->getKey())
            ->first();

        if ($lock === null || $lock->isExpired()) {
            return ContentLock::updateOrCreate(
                ['lockable_type' => $entry->getMorphClass(), 'lockable_id' => $entry->getKey()],
                ['user_id' => $actor->getKey()],
            );
        }

        if ((int) $lock->user_id === $actor->getKey()) {
            $lock->touch();

            return $lock;
        }

        throw ContentLockedException::heldBy($lock->user()->firstOrFail());
    }
}
