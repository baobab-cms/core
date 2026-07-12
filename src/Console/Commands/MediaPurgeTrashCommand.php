<?php

declare(strict_types=1);

namespace Baobab\Console\Commands;

use Baobab\Media\Actions\PurgeMedia;
use Baobab\Media\Models\Media;
use Illuminate\Console\Command;

/**
 * Purge programmée de la corbeille média (M4 point 3, spec 06 §1.2) —
 * supprime définitivement (fichiers + ligne) tout média mis à la corbeille
 * depuis plus de `baobab.media.trash_retention_days` (défaut 30). Enregistrée
 * quotidiennement dans le scheduler depuis BaobabServiceProvider.
 */
final class MediaPurgeTrashCommand extends Command
{
    protected $signature = 'media:purge-trash';

    protected $description = 'Purge définitivement les médias en corbeille depuis plus longtemps que la rétention configurée.';

    public function handle(PurgeMedia $purge): int
    {
        $retentionDays = (int) config('baobab.media.trash_retention_days', 30);
        $count = 0;

        Media::onlyTrashed()
            ->where('deleted_at', '<=', now()->subDays($retentionDays))
            ->each(function (Media $media) use ($purge, &$count): void {
                $purge($media);
                $count++;
            });

        $this->info("{$count} média(s) purgé(s) définitivement.");

        return self::SUCCESS;
    }
}
