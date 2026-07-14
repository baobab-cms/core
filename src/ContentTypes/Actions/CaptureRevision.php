<?php

declare(strict_types=1);

namespace Baobab\ContentTypes\Actions;

use Baobab\ContentTypes\Editorial\Models\Revision;
use Baobab\ContentTypes\Models\ContentType;
use Baobab\Users\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Snapshot d'un contenu (spec 09 §6) — appelée depuis l'intérieur de
 * SaveContentEntry (sauvegarde effective) et des transitions de publication
 * (publish/schedule/approve), jamais par le contrôleur directement : la
 * capture fait partie de l'action elle-même, comme les hooks et l'audit.
 * Sans effet si le type a désactivé les révisions (`revisionsLimit() === 0`).
 * Le quota (révisions `manual` uniquement — autosave/working_draft sont déjà
 * singletons, pre_restore toujours conservées) est purgé en queue.
 */
final class CaptureRevision
{
    public function __invoke(ContentType $contentType, Model $entry, string $type, ?User $actor = null, ?string $summary = null): ?Revision
    {
        if (! $contentType->revisionsEnabled()) {
            return null;
        }

        $revision = Revision::create([
            'revisionable_type' => $entry->getMorphClass(),
            'revisionable_id' => $entry->getKey(),
            'type' => $type,
            'snapshot' => $this->snapshotFor($contentType, $entry),
            'summary' => $summary,
            'author_id' => $actor?->getKey(),
        ]);

        if ($type === 'manual') {
            PurgeOldRevisions::dispatch($entry->getMorphClass(), $entry->getKey(), $contentType->revisionsLimit());
        }

        return $revision;
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshotFor(ContentType $contentType, Model $entry): array
    {
        return collect($entry->attributesToArray())
            ->except($contentType->revisionsExcept())
            ->all();
    }
}
