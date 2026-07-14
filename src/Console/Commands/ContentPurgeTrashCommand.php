<?php

declare(strict_types=1);

namespace Baobab\Console\Commands;

use Baobab\ContentTypes\Actions\PurgeContentEntry;
use Baobab\ContentTypes\Models\ContentType;
use Baobab\ContentTypes\Support\ContentTrash;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

/**
 * Purge programmée de la corbeille de contenu (spec 09 §8, M5 point 2) —
 * supprime définitivement tout contenu mis à la corbeille depuis plus de
 * `baobab.content.trash_retention_days` (défaut 30). Enregistrée chaque
 * jour par Baobab\Scheduler\SchedulerRegistrar, même patron que
 * `media:purge-trash`.
 */
final class ContentPurgeTrashCommand extends Command
{
    protected $signature = 'content:purge-trash';

    protected $description = 'Purge définitivement les contenus en corbeille depuis plus longtemps que la rétention configurée.';

    public function handle(PurgeContentEntry $purge): int
    {
        $retentionDays = (int) config('baobab.content.trash_retention_days', 30);
        $count = 0;

        foreach (ContentType::whereNotNull('module_id')->get() as $contentType) {
            /** @var class-string<Model> $modelClass */
            $modelClass = $contentType->modelClass();

            if (! class_exists($modelClass)) {
                continue;
            }

            ContentTrash::onlyTrashed($modelClass)
                ->where('deleted_at', '<=', now()->subDays($retentionDays))
                ->each(function (Model $entry) use ($purge, $contentType, &$count): void {
                    $purge($contentType, $entry);
                    $count++;
                });
        }

        $this->info("{$count} contenu(s) purgé(s) définitivement.");

        return self::SUCCESS;
    }
}
