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
 * `pending` → `draft` (spec 09 §2.2, §5) : rejet d'une soumission. Le
 * commentaire est obligatoire (« un rejet muet est inutilisable ») —
 * conservé dans l'entrée d'audit ; le fil visible dans le formulaire de
 * contenu (historique des allers-retours) est du ressort de M5 point 3.
 */
final class RejectContentEntry
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly ContentStateMachine $machine,
    ) {}

    public function __invoke(ContentType $contentType, Model $entry, string $comment, ?User $actor = null): Model
    {
        $from = (string) $entry->getAttribute('status');
        $this->machine->assertAllowed($contentType, $from, 'reject');

        $entry->update(['status' => 'draft']);

        $this->audit->record('content.rejected', $entry, ['content_type' => $contentType->key, 'comment' => $comment]);

        Hook::action('baobab.content.rejected', $contentType, $entry, $comment);
        Hook::action('baobab.content.transitioned', $contentType, $entry, $from, 'draft', $actor);

        return $entry;
    }
}
