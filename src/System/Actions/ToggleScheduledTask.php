<?php

declare(strict_types=1);

namespace Baobab\System\Actions;

use Baobab\Audit\AuditLogger;
use Baobab\Scheduler\Models\ScheduledTaskSuspension;
use Baobab\Scheduler\SchedulerRegistrar;
use InvalidArgumentException;

/**
 * Suspendre/réactiver une tâche de module (spec 12 §2.3) — jamais une tâche
 * Core, gardé ici ET côté vue (le bouton n'existe pas pour une tâche Core,
 * patron du renvoi d'e-mail : « le bouton est déjà grisé »).
 */
final class ToggleScheduledTask
{
    public function __construct(
        private readonly SchedulerRegistrar $registrar,
        private readonly AuditLogger $audit,
    ) {}

    public function suspend(string $taskKey): void
    {
        $this->guardAgainstCoreTask($taskKey);

        ScheduledTaskSuspension::firstOrCreate(['task_key' => $taskKey]);

        $this->audit->record('scheduler.task.suspended', null, ['task_key' => $taskKey]);
    }

    public function resume(string $taskKey): void
    {
        $this->guardAgainstCoreTask($taskKey);

        ScheduledTaskSuspension::where('task_key', $taskKey)->delete();

        $this->audit->record('scheduler.task.resumed', null, ['task_key' => $taskKey]);
    }

    private function guardAgainstCoreTask(string $taskKey): void
    {
        if ($this->registrar->isCoreTask($taskKey)) {
            throw new InvalidArgumentException("Une tâche Core ne peut pas être suspendue : {$taskKey}");
        }
    }
}
