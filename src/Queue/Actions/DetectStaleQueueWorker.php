<?php

declare(strict_types=1);

namespace Baobab\Queue\Actions;

use Illuminate\Support\Facades\DB;

/**
 * Minutes écoulées depuis la mise en attente du plus ancien job toutes
 * queues confondues, si elles dépassent `baobab.queues.stale_worker_minutes`
 * (défaut 5) — `null` sinon (aucune file en attente, ou worker actif).
 * Extrait de `QueuesController` (M9 chantier 0.a Pass C) pour être réutilisé
 * tel quel par le contrôle de santé « Queues » (spec 12 §7.2, Pass E) — même
 * seuil, même détection, une seule source de vérité.
 */
final class DetectStaleQueueWorker
{
    public function __invoke(): ?int
    {
        $oldestAvailableAt = DB::table('jobs')->min('available_at');

        if ($oldestAvailableAt === null) {
            return null;
        }

        $minutes = (int) floor((now()->getTimestamp() - (int) $oldestAvailableAt) / 60);

        return $minutes >= (int) config('baobab.queues.stale_worker_minutes', 5) ? $minutes : null;
    }
}
