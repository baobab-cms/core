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
 * `draft`/`pending`/`scheduled` → `scheduled` (spec 09 §2.2, §4) : publication
 * programmée à `$publishAt` (reprogrammer un contenu déjà `scheduled` est
 * permis, même transition). Le caractère futur de la date est vérifié à la
 * frontière HTTP (règle de validation `after:now`) — cette Action fait
 * confiance à son appelant, comme le reste de la couche Actions.
 */
final class ScheduleContentEntry
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly ContentStateMachine $machine,
        private readonly CaptureRevision $captureRevision,
    ) {}

    public function __invoke(ContentType $contentType, Model $entry, DateTimeInterface $publishAt, ?User $actor = null): Model
    {
        $from = (string) $entry->getAttribute('status');
        $this->machine->assertAllowed($contentType, $from, 'schedule');

        $entry->update(['status' => 'scheduled', 'published_at' => $publishAt]);

        $this->audit->record('content.scheduled', $entry, ['content_type' => $contentType->key, 'published_at' => $publishAt]);

        ($this->captureRevision)($contentType, $entry, 'manual', $actor, 'Programmation');

        Hook::action('baobab.content.scheduled', $contentType, $entry);
        Hook::action('baobab.content.transitioned', $contentType, $entry, $from, 'scheduled', $actor);

        return $entry;
    }
}
