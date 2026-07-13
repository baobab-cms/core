<?php

declare(strict_types=1);

namespace Baobab\ContentTypes\Actions;

use Baobab\Audit\AuditLogger;
use Baobab\ContentTypes\Editorial\ContentStateMachine;
use Baobab\ContentTypes\Models\ContentType;
use Baobab\Facades\Hook;
use Baobab\Users\Models\User;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * `pending` → `published` ou `scheduled` (spec 09 §2.2, §5) : un examinateur
 * approuve une soumission — immédiatement si aucune date n'est fournie, ou
 * programmée si `$publishAt` est future. Toujours `publish_any` (jamais
 * own — vérifié par l'appelant, on n'approuve pas sa propre soumission).
 */
final class ApproveContentEntry
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly ContentStateMachine $machine,
    ) {}

    public function __invoke(ContentType $contentType, Model $entry, ?DateTimeInterface $publishAt = null, ?User $actor = null): Model
    {
        $from = (string) $entry->getAttribute('status');
        $this->machine->assertAllowed($contentType, $from, 'approve');

        $to = $publishAt !== null && $publishAt > now() ? 'scheduled' : 'published';

        $entry->update(['status' => $to, 'published_at' => $publishAt ?? now()]);

        $this->audit->record('content.approved', $entry, ['content_type' => $contentType->key, 'to' => $to]);

        Hook::action('baobab.content.approved', $contentType, $entry);
        Hook::action('baobab.content.transitioned', $contentType, $entry, $from, $to, $actor);

        return $entry;
    }
}
