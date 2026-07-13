<?php

declare(strict_types=1);

namespace Baobab\Console\Commands;

use Baobab\ContentTypes\Actions\PublishScheduledContentEntries;
use Illuminate\Console\Command;

/**
 * Publication programmée (spec 09 §4, M5 point 1) — bascule en `published`
 * tout contenu `scheduled` dont `published_at` est atteint. Enregistrée
 * chaque minute par Baobab\Scheduler\SchedulerRegistrar.
 */
final class ContentPublishDueCommand extends Command
{
    protected $signature = 'content:publish-due';

    protected $description = 'Publie les contenus programmés dont l\'échéance est atteinte.';

    public function handle(PublishScheduledContentEntries $publish): int
    {
        $count = $publish();

        $this->info("{$count} contenu(s) publié(s).");

        return self::SUCCESS;
    }
}
