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
 * Applique une révision de travail au contenu (spec 09 §3 : « via l'action
 * publish, mêmes permissions » — vérifié par l'appelant). N'est **pas** une
 * transition d'état : le statut ne change pas (le contenu était déjà
 * `published`/`scheduled`, c'est pour ça qu'un working draft existait) —
 * seuls les champs de contenu du snapshot sont appliqués, jamais
 * statut/dates/paternité qui restent gouvernés par la machine à états et
 * la propriété d'origine.
 */
final class PublishWorkingDraftEntry
{
    /** @var list<string> */
    private const PROTECTED_KEYS = ['id', 'status', 'published_at', 'unpublish_at', 'author_id', 'created_at', 'updated_at', 'deleted_at'];

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly CaptureRevision $captureRevision,
    ) {}

    public function __invoke(ContentType $contentType, Model $entry, Revision $draft, User $actor): Model
    {
        $fields = collect($draft->snapshot)->except(self::PROTECTED_KEYS)->all();

        $entry->fill($fields);
        $entry->save();

        $draft->delete();

        $this->audit->record('content.working_draft.published', $entry, ['content_type' => $contentType->key]);

        ($this->captureRevision)($contentType, $entry, 'manual', $actor, 'Brouillon publié');

        Hook::action('baobab.content.working_draft.published', $contentType, $entry);

        return $entry;
    }
}
