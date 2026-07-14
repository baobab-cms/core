<?php

declare(strict_types=1);

namespace Baobab\ContentTypes\Actions;

use Baobab\ContentTypes\Editorial\Models\ContentLock;
use Baobab\Users\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Libère le verrou détenu par l'appelant à la fermeture propre du
 * formulaire (spec 09 §7) — ne libère jamais le verrou d'un autre
 * utilisateur (c'est le rôle de TakeOverContentLock).
 */
final class ReleaseContentLock
{
    public function __invoke(Model $entry, User $actor): void
    {
        ContentLock::where('lockable_type', $entry->getMorphClass())
            ->where('lockable_id', $entry->getKey())
            ->where('user_id', $actor->getKey())
            ->delete();
    }
}
