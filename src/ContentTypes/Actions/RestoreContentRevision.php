<?php

declare(strict_types=1);

namespace Baobab\ContentTypes\Actions;

use Baobab\Audit\AuditLogger;
use Baobab\ContentTypes\Editorial\Models\Revision;
use Baobab\ContentTypes\Models\ContentType;
use Baobab\Facades\Hook;
use Baobab\Users\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Restaure une révision passée (spec 09 §6) : capture d'abord une révision
 * `pre_restore` de l'état courant (restaurer ne détruit jamais d'historique),
 * puis applique le snapshot choisi comme une nouvelle sauvegarde — « ne
 * remonte pas le temps ». Seuls les champs de contenu sont restaurés, jamais
 * statut/dates/paternité (même principe que PublishWorkingDraftEntry) : le
 * statut reste gouverné par la machine à états, pas par un ancien snapshot.
 */
final class RestoreContentRevision
{
    /** @var list<string> */
    private const PROTECTED_KEYS = ['id', 'status', 'published_at', 'unpublish_at', 'author_id', 'created_at', 'updated_at', 'deleted_at'];

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly CaptureRevision $captureRevision,
    ) {}

    public function __invoke(ContentType $contentType, Model $entry, Revision $revision, User $actor): Model
    {
        ($this->captureRevision)($contentType, $entry, 'pre_restore', $actor, 'Avant restauration');

        $fields = collect($revision->snapshot)->except(self::PROTECTED_KEYS)->all();
        $entry->fill($fields);
        $entry->save();

        $this->audit->record('content.revision_restored', $entry, ['content_type' => $contentType->key, 'revision_id' => $revision->id]);

        ($this->captureRevision)($contentType, $entry, 'manual', $actor, "Restauration de la révision #{$revision->id}");

        Hook::action('baobab.content.revision_restored', $contentType, $entry, $revision);

        return $entry;
    }
}
