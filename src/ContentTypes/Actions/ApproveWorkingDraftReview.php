<?php

declare(strict_types=1);

namespace Baobab\ContentTypes\Actions;

use Baobab\Audit\AuditLogger;
use Baobab\ContentTypes\Editorial\Models\Revision;
use Baobab\ContentTypes\Exceptions\InvalidContentTransitionException;
use Baobab\ContentTypes\Models\ContentType;
use Baobab\Facades\Hook;
use Baobab\Users\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Approuve un working draft soumis à validation (spec 09 §3 dernière puce,
 * §5) : applique le snapshot au contenu, comme `PublishWorkingDraftEntry`,
 * mais réservé aux working drafts `pending` — jamais own, toujours
 * `publish_any` (vérifié par l'appelant). N'est pas une transition de la
 * machine à états : le statut de la ligne ne bouge pas, elle était déjà
 * `published`/`scheduled`.
 */
final class ApproveWorkingDraftReview
{
    /** @var list<string> */
    private const PROTECTED_KEYS = ['id', 'status', 'published_at', 'unpublish_at', 'author_id', 'created_at', 'updated_at', 'deleted_at'];

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly CaptureRevision $captureRevision,
    ) {}

    public function __invoke(ContentType $contentType, Model $entry, Revision $draft, User $actor): Model
    {
        if ($draft->type !== 'pending') {
            throw InvalidContentTransitionException::forTransition('approve', $draft->type);
        }

        $fields = collect($draft->snapshot)->except(self::PROTECTED_KEYS)->all();

        $entry->fill($fields);
        $entry->save();

        $draft->delete();

        $this->audit->record('content.working_draft.approved', $entry, ['content_type' => $contentType->key]);

        ($this->captureRevision)($contentType, $entry, 'manual', $actor, 'Approbation du brouillon');

        Hook::action('baobab.content.working_draft.approved', $contentType, $entry);

        return $entry;
    }
}
