<?php

declare(strict_types=1);

namespace Baobab\ContentTypes\Actions;

use Baobab\ContentTypes\Editorial\Models\Revision;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Purge les révisions `manual` d'un contenu au-delà du quota (spec 09 §6,
 * spec 02 §9 décision 4) — garde les N plus récentes, où N est le quota
 * effectif au moment de l'appel (blueprint ou `baobab.content.revisions_limit`).
 * Ne touche jamais `autosave`/`working_draft` (déjà singletons) ni
 * `pre_restore` (toujours conservées). En queue — CaptureRevision la
 * dispatch après chaque nouvelle révision manuelle, pas d'effet immédiat
 * requis.
 */
final class PurgeOldRevisions implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        private readonly string $revisionableType,
        private readonly int|string $revisionableId,
        private readonly int $limit,
    ) {}

    public function handle(): void
    {
        if ($this->limit <= 0) {
            return;
        }

        $idsToKeep = Revision::where('revisionable_type', $this->revisionableType)
            ->where('revisionable_id', $this->revisionableId)
            ->where('type', 'manual')
            ->orderByDesc('id')
            ->limit($this->limit)
            ->pluck('id');

        Revision::where('revisionable_type', $this->revisionableType)
            ->where('revisionable_id', $this->revisionableId)
            ->where('type', 'manual')
            ->whereNotIn('id', $idsToKeep)
            ->delete();
    }
}
