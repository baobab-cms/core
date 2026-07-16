<?php

declare(strict_types=1);

namespace Baobab\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Purge programmée des notifications (spec 11 §8) — supprime toute
 * notification (lue ou non) plus ancienne que
 * `baobab.notifications.retention_days` (défaut 90). Enregistrée
 * quotidiennement dans `SchedulerRegistrar`, même patron que
 * `media:purge-trash`/`content:purge-trash`.
 */
final class NotificationsPurgeCommand extends Command
{
    protected $signature = 'notifications:purge-old';

    protected $description = 'Supprime les notifications plus anciennes que la rétention configurée.';

    public function handle(): int
    {
        $retentionDays = (int) config('baobab.notifications.retention_days', 90);

        $count = DatabaseNotification::where('created_at', '<=', now()->subDays($retentionDays))->delete();

        $this->info("{$count} notification(s) purgée(s).");

        return self::SUCCESS;
    }
}
