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
 * `draft` → `pending` (spec 09 §2.2) : un auteur sans droit de publication
 * soumet son contenu à validation. Autorisation vérifiée par l'appelant
 * (policy `update`, own/any) — cette Action ne fait confiance qu'à la
 * machine à états.
 */
final class SubmitContentEntry
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly ContentStateMachine $machine,
    ) {}

    public function __invoke(ContentType $contentType, Model $entry, ?User $actor = null): Model
    {
        $from = (string) $entry->getAttribute('status');
        $this->machine->assertAllowed($contentType, $from, 'submit');

        $entry->update(['status' => 'pending']);

        $this->audit->record('content.submitted', $entry, ['content_type' => $contentType->key]);

        Hook::action('baobab.content.submitted', $contentType, $entry);
        Hook::action('baobab.content.transitioned', $contentType, $entry, $from, 'pending', $actor);

        return $entry;
    }
}
