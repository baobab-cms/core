<?php

declare(strict_types=1);

namespace Baobab\Scheduler;

use Baobab\Console\Commands\ContentPublishDueCommand;
use Baobab\Console\Commands\ContentUnpublishDueCommand;
use Baobab\Modules\Models\Module;
use Baobab\Scheduler\Models\ScheduledTaskRun;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Moteur de scheduler (spec 12 §2, M5 point 7) : enregistre les tâches Core
 * et toute tâche déclarée par un module actif (`schedule` au manifeste,
 * même champ pour tous — pas de traitement spécial pour le Core), et
 * journalise chaque exécution dans `scheduled_task_runs`. « Enregistrement/
 * désenregistrement au cycle de vie du module » se résout naturellement ici
 * : seuls les modules actifs sont parcourus, comme
 * BaobabServiceProvider::bootstrapActiveModules(). L'UI d'administration
 * (admin/system/scheduler) et l'alerte santé restent hors périmètre (M9).
 */
final class SchedulerRegistrar
{
    public function register(Schedule $schedule): void
    {
        $this->record($schedule->command(ContentPublishDueCommand::class)->everyMinute(), 'baobab.content.publish-due');
        $this->record($schedule->command(ContentUnpublishDueCommand::class)->everyMinute(), 'baobab.content.unpublish-due');

        $this->registerModuleTasks($schedule);
    }

    /**
     * Base pas forcément migrée à cet instant (installateur, tests, boot
     * anticipé) — même garde que
     * BaobabServiceProvider::bootstrapActiveModules().
     */
    private function registerModuleTasks(Schedule $schedule): void
    {
        try {
            if (! Schema::hasTable('modules')) {
                return;
            }

            foreach (Module::where('status', 'active')->get() as $module) {
                foreach ($module->manifest['schedule'] ?? [] as $task) {
                    $this->record($schedule->command($task['command'])->cron($task['cron']), $task['key']);
                }
            }
        } catch (Throwable) {
            // DB injoignable — les tâches Core restent enregistrées, celles des modules attendront le prochain boot.
        }
    }

    private function record(Event $event, string $taskKey): void
    {
        $event
            ->before(fn () => $this->recordStart($taskKey))
            ->onSuccess(fn () => $this->recordFinish($taskKey, 'success'))
            ->onFailure(fn () => $this->recordFinish($taskKey, 'failed'));
    }

    /**
     * Extraites en méthodes publiques (plutôt que des closures inline) pour
     * rester testables sans devoir exécuter un vrai processus planifié —
     * SchedulerRegistrarTest les appelle directement.
     */
    public function recordStart(string $taskKey): void
    {
        ScheduledTaskRun::create([
            'task_key' => $taskKey,
            'started_at' => now(),
            'status' => 'running',
        ]);
    }

    public function recordFinish(string $taskKey, string $status): void
    {
        ScheduledTaskRun::where('task_key', $taskKey)
            ->where('status', 'running')
            ->latest('id')
            ->first()
            ?->update(['status' => $status, 'finished_at' => now()]);
    }
}
