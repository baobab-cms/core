<?php

declare(strict_types=1);

namespace Baobab\ContentTypes\Actions;

use Baobab\Audit\AuditLogger;
use Baobab\ContentTypes\Editorial\Models\Revision;
use Baobab\ContentTypes\Exceptions\InvalidContentTransitionException;
use Baobab\ContentTypes\Models\ContentType;
use Baobab\Facades\Hook;

/**
 * Rejette un working draft soumis à validation (spec 09 §3 dernière puce,
 * §5) : le brouillon redevient éditable (`working_draft`), rien n'est perdu
 * — contrairement au rejet d'une soumission native qui renvoie le contenu en
 * `draft`, ici la ligne `ct_*` reste inchangée puisqu'elle n'a jamais bougé.
 * Le commentaire est obligatoire, conservé dans l'entrée d'audit — même fil
 * que `RejectContentEntry`, affiché dans le formulaire de contenu.
 */
final class RejectWorkingDraftReview
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function __invoke(ContentType $contentType, Revision $draft, string $comment): Revision
    {
        if ($draft->type !== 'pending') {
            throw InvalidContentTransitionException::forTransition('reject', $draft->type);
        }

        $draft->update(['type' => 'working_draft']);

        $this->audit->record('content.working_draft.rejected', $draft->revisionable, ['content_type' => $contentType->key, 'comment' => $comment]);

        Hook::action('baobab.content.working_draft.rejected', $contentType, $draft->revisionable, $comment);

        return $draft;
    }
}
