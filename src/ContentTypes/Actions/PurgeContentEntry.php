<?php

declare(strict_types=1);

namespace Baobab\ContentTypes\Actions;

use Baobab\Audit\AuditLogger;
use Baobab\ContentTypes\Editorial\Models\ContentLock;
use Baobab\ContentTypes\Editorial\Models\Revision;
use Baobab\ContentTypes\Models\ContentType;
use Baobab\Facades\Hook;
use Illuminate\Database\Eloquent\Model;

/**
 * Purge définitive d'un contenu en corbeille (spec 09 §8) — supprime aussi
 * ses révisions et son verrou éventuel (état orphelin sinon). Autorisation
 * vérifiée par l'appelant (`baobab.trash.purge`, permission globale).
 */
final class PurgeContentEntry
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function __invoke(ContentType $contentType, Model $entry): void
    {
        Revision::where('revisionable_type', $entry->getMorphClass())
            ->where('revisionable_id', $entry->getKey())
            ->delete();

        ContentLock::where('lockable_type', $entry->getMorphClass())
            ->where('lockable_id', $entry->getKey())
            ->delete();

        $this->audit->record('content.purged', $entry, ['content_type' => $contentType->key]);

        Hook::action('baobab.content.purged', $contentType, $entry);

        $entry->forceDelete();
    }
}
