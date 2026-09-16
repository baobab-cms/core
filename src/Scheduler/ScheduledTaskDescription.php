<?php

declare(strict_types=1);

namespace Baobab\Scheduler;

use Baobab\Scheduler\Models\ScheduledTaskRun;
use DateTimeInterface;

/**
 * Métadonnées d'une tâche planifiée pour l'écran admin/system/scheduler
 * (spec 12 §2.3) — retournée par SchedulerRegistrar::describeTasks(), aucun
 * effet de bord. `lastRun` est `null` tant que la tâche n'a jamais tourné ;
 * `nextRun` est `null` quand la tâche est suspendue (elle ne tournera pas).
 */
final readonly class ScheduledTaskDescription
{
    public function __construct(
        public string $taskKey,
        public string $source,
        public ?string $moduleName,
        public ?string $description,
        public string $command,
        public string $cron,
        public string $cronHuman,
        public bool $suspended,
        public ?ScheduledTaskRun $lastRun,
        public ?DateTimeInterface $nextRun,
    ) {}
}
