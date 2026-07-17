<?php

declare(strict_types=1);

namespace Baobab\Console\Commands;

use Baobab\Seo\Models\NotFoundHit;
use Illuminate\Console\Command;

/**
 * Purge programmée du journal des 404 (spec 07 §4) — supprime définitivement
 * toute entrée dont `last_hit_at` dépasse `baobab.redirects.not_found_retention_days`
 * (défaut 90). Enregistrée quotidiennement dans le scheduler depuis
 * BaobabServiceProvider, patron `media:purge-trash`.
 */
final class NotFoundPurgeCommand extends Command
{
    protected $signature = 'seo:purge-404-log';

    protected $description = 'Purge définitivement les entrées du journal des 404 antérieures à la rétention configurée.';

    public function handle(): int
    {
        $retentionDays = (int) config('baobab.redirects.not_found_retention_days', 90);

        $count = NotFoundHit::where('last_hit_at', '<=', now()->subDays($retentionDays))->delete();

        $this->info("{$count} entrée(s) du journal des 404 purgée(s).");

        return self::SUCCESS;
    }
}
