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
 * `archived` → `draft` (spec 09 §2.2) : sortie d'archive. Nommée
 * distinctement de la restauration-corbeille (soft delete, M5 point 2) pour
 * éviter toute collision de classe malgré le même verbe métier « restore »
 * côté spec — le hook émis, lui, reste `baobab.content.restored`, fidèle à
 * la spec.
 */
final class RestoreArchivedContentEntry
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly ContentStateMachine $machine,
    ) {}

    public function __invoke(ContentType $contentType, Model $entry, ?User $actor = null): Model
    {
        $from = (string) $entry->getAttribute('status');
        $this->machine->assertAllowed($contentType, $from, 'restore');

        $entry->update(['status' => 'draft']);

        $this->audit->record('content.restored', $entry, ['content_type' => $contentType->key]);

        Hook::action('baobab.content.restored', $contentType, $entry);
        Hook::action('baobab.content.transitioned', $contentType, $entry, $from, 'draft', $actor);

        return $entry;
    }
}
