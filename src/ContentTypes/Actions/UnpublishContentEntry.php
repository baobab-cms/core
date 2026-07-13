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
 * `published`/`scheduled` → `draft` (spec 09 §2.2) : retrait de la
 * publication. `published_at` est remis à `null` — un `draft` n'a pas de
 * date de publication en attente ; republier (PublishContentEntry) en pose
 * toujours une nouvelle.
 */
final class UnpublishContentEntry
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly ContentStateMachine $machine,
    ) {}

    public function __invoke(ContentType $contentType, Model $entry, ?User $actor = null): Model
    {
        $from = (string) $entry->getAttribute('status');
        $this->machine->assertAllowed($contentType, $from, 'unpublish');

        $entry->update(['status' => 'draft', 'published_at' => null]);

        $this->audit->record('content.unpublished', $entry, ['content_type' => $contentType->key]);

        Hook::action('baobab.content.unpublished', $contentType, $entry);
        Hook::action('baobab.content.transitioned', $contentType, $entry, $from, 'draft', $actor);

        return $entry;
    }
}
