<?php

declare(strict_types=1);

namespace Baobab\ContentTypes\Actions;

use Baobab\Audit\AuditLogger;
use Baobab\ContentTypes\Editorial\ContentStateMachine;
use Baobab\ContentTypes\Models\ContentType;
use Baobab\Facades\Hook;
use Baobab\Users\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * `draft`/`pending`/`published`/`scheduled` → `archived` (spec 09 §2.2) :
 * retiré de la publication mais conservé (plus d'URL publique). Distinct de
 * la corbeille (soft delete, M5 point 2) — un contenu archivé garde son
 * `deleted_at` à null et peut être restauré (RestoreArchivedContentEntry).
 */
final class ArchiveContentEntry
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly ContentStateMachine $machine,
    ) {}

    public function __invoke(ContentType $contentType, Model $entry, ?User $actor = null): Model
    {
        $from = (string) $entry->getAttribute('status');
        $this->machine->assertAllowed($contentType, $from, 'archive');

        $entry->update(['status' => 'archived']);

        $this->audit->record('content.archived', $entry, ['content_type' => $contentType->key]);

        Hook::action('baobab.content.archived', $contentType, $entry);
        Hook::action('baobab.content.transitioned', $contentType, $entry, $from, 'archived', $actor);

        return $entry;
    }
}
