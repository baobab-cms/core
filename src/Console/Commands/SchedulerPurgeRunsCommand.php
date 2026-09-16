<?php

declare(strict_types=1);

namespace Baobab\Console\Commands;

use Baobab\Scheduler\Models\ScheduledTaskRun;
use Illuminate\Console\Command;

/**
 * Purge programmée de l'historique d'exécution du scheduler (spec 12 §2.3) —
 * supprime définitivement toute ligne dont `started_at` dépasse
 * `baobab.scheduler.run_retention_days` (défaut 30). Enregistrée
 * quotidiennement par SchedulerRegistrar, patron `seo:purge-404-log`.
 */
final class SchedulerPurgeRunsCommand extends Command
{
    protected $signature = 'baobab:scheduler:purge-runs';

    protected $description = "Purge définitivement l'historique d'exécution du scheduler antérieur à la rétention configurée.";

    public function handle(): int
    {
        $retentionDays = (int) config('baobab.scheduler.run_retention_days', 30);

        $count = ScheduledTaskRun::where('started_at', '<=', now()->subDays($retentionDays))->delete();

        $this->info("{$count} ligne(s) d'historique du scheduler purgée(s).");

        return self::SUCCESS;
    }
}
