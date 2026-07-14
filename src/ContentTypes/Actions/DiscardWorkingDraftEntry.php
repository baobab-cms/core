<?php

declare(strict_types=1);

namespace Baobab\ContentTypes\Actions;

use Baobab\Audit\AuditLogger;
use Baobab\ContentTypes\Editorial\Models\Revision;
use Baobab\ContentTypes\Models\ContentType;
use Baobab\Facades\Hook;

/**
 * Abandonne une révision de travail (spec 09 §3) — la version publiée reste
 * inchangée, il n'y a jamais eu d'écriture sur `ct_*` à annuler.
 */
final class DiscardWorkingDraftEntry
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function __invoke(ContentType $contentType, Revision $draft): void
    {
        $revisionable = $draft->revisionable;

        $draft->delete();

        $this->audit->record('content.working_draft.discarded', $revisionable, ['content_type' => $contentType->key]);

        Hook::action('baobab.content.working_draft.discarded', $contentType, $revisionable);
    }
}
