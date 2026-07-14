<?php

declare(strict_types=1);

namespace Baobab\ContentTypes\Actions;

use Baobab\Audit\AuditLogger;
use Baobab\ContentTypes\Editorial\Models\Revision;
use Baobab\ContentTypes\Exceptions\InvalidContentTransitionException;
use Baobab\ContentTypes\Models\ContentType;
use Baobab\Facades\Hook;

/**
 * Soumet à validation la modification d'un contenu déjà publié (spec 09 §3
 * dernière puce, §5) : le `pending` porte sur le working draft, jamais sur
 * la ligne `ct_*` — le statut du contenu ne change pas, il reste visible au
 * public inchangé. N'est pas une transition de la machine à états (spec 09
 * §2.2) : celle-ci ne connaît que les statuts de la ligne, pas ceux du
 * brouillon de travail.
 */
final class SubmitWorkingDraftForReview
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function __invoke(ContentType $contentType, Revision $draft): Revision
    {
        if ($draft->type !== 'working_draft' || ! $contentType->workflowEnabled()) {
            throw InvalidContentTransitionException::forTransition('submit', $draft->type);
        }

        $draft->update(['type' => 'pending']);

        $this->audit->record('content.working_draft.submitted', $draft->revisionable, ['content_type' => $contentType->key]);

        Hook::action('baobab.content.working_draft.submitted', $contentType, $draft->revisionable, $draft);

        return $draft;
    }
}
