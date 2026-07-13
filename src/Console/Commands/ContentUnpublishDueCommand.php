<?php

declare(strict_types=1);

namespace Baobab\Console\Commands;

use Baobab\ContentTypes\Actions\ArchiveDueContentEntries;
use Illuminate\Console\Command;

/**
 * Dépublication programmée (spec 09 §4, M5 point 1) — bascule en `archived`
 * tout contenu `published` dont `unpublish_at` est atteint. Enregistrée
 * chaque minute par Baobab\Scheduler\SchedulerRegistrar.
 */
final class ContentUnpublishDueCommand extends Command
{
    protected $signature = 'content:unpublish-due';

    protected $description = 'Archive les contenus dont la dépublication programmée est atteinte.';

    public function handle(ArchiveDueContentEntries $archive): int
    {
        $count = $archive();

        $this->info("{$count} contenu(s) archivé(s).");

        return self::SUCCESS;
    }
}
