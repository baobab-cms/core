<?php

declare(strict_types=1);

namespace Baobab\Scheduler;

use Baobab\Backups\Models\BackupSetting;
use Baobab\Console\Commands\AuditPurgeCommand;
use Baobab\Console\Commands\BackupRunCommand;
use Baobab\Console\Commands\ContentPublishDueCommand;
use Baobab\Console\Commands\ContentPurgeTrashCommand;
use Baobab\Console\Commands\ContentUnpublishDueCommand;
use Baobab\Console\Commands\FormSubmissionsPurgeCommand;
use Baobab\Console\Commands\HealthCheckCommand;
use Baobab\Console\Commands\MailLogPurgeCommand;
use Baobab\Console\Commands\MediaPurgeTrashCommand;
use Baobab\Console\Commands\NotFoundPurgeCommand;
use Baobab\Console\Commands\NotificationsPurgeCommand;
use Baobab\Console\Commands\PrivacyPurgeExportsCommand;
use Baobab\Console\Commands\PrivacyRunDueErasuresCommand;
use Baobab\Console\Commands\SchedulerPurgeRunsCommand;
use Baobab\Facades\Hook;
use Baobab\Modules\Models\Module;
use Baobab\Scheduler\Models\ScheduledTaskRun;
use Baobab\Scheduler\Models\ScheduledTaskSuspension;
use Baobab\Scheduler\Support\CronHumanizer;
use Cron\CronExpression;
use DateTimeInterface;
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
 * BaobabServiceProvider::bootstrapActiveModules(). `describeTasks()`,
 * `isCoreTask()` et `commandFor()` (M9 chantier 0.a Pass B) exposent les
 * mêmes définitions sans effet de bord, pour l'écran admin/system/scheduler
 * et pour RunScheduledTask/ToggleScheduledTask.
 */
final class SchedulerRegistrar
{
    public function register(Schedule $schedule): void
    {
        foreach ($this->coreTaskDefinitions() as $definition) {
            $event = $schedule->command($definition['command'])->cron($definition['cron']);

            // Seule tâche Core « désactivable » de la spec (§4.2, décision de
            // séance du 17 septembre 2026) — un artisan baobab:backup ou un
            // « Exécuter maintenant » explicites restent toujours actifs,
            // BackupRunCommand ne consulte jamais ce réglage lui-même ; seul
            // le vrai déclenchement planifié le respecte.
            if ($definition['key'] === 'baobab.backups.create') {
                $event->when(static fn (): bool => BackupSetting::current()->scheduled_enabled);
            }

            $this->record($event, $definition['key']);
        }

        $this->registerModuleTasks($schedule);
    }

    /**
     * @return list<ScheduledTaskDescription>
     */
    public function describeTasks(): array
    {
        $suspended = $this->suspendedTaskKeys();

        $definitions = array_map(
            fn (array $definition): array => [...$definition, 'source' => 'core', 'module_name' => null],
            $this->coreTaskDefinitions(),
        );

        $definitions = [...$definitions, ...$this->activeModuleTasks()];

        $lastRuns = ScheduledTaskRun::whereIn('task_key', array_column($definitions, 'key'))
            ->orderByDesc('id')
            ->get()
            ->groupBy('task_key');

        return array_map(function (array $definition) use ($suspended, $lastRuns): ScheduledTaskDescription {
            $isSuspended = in_array($definition['key'], $suspended, true);

            return new ScheduledTaskDescription(
                taskKey: $definition['key'],
                source: $definition['source'],
                moduleName: $definition['module_name'],
                description: $definition['description'],
                command: $definition['command'],
                cron: $definition['cron'],
                cronHuman: CronHumanizer::describe($definition['cron']),
                suspended: $isSuspended,
                lastRun: $lastRuns->get($definition['key'])?->first(),
                nextRun: $isSuspended ? null : $this->nextRunDate($definition['cron']),
            );
        }, $definitions);
    }

    public function isCoreTask(string $taskKey): bool
    {
        foreach ($this->coreTaskDefinitions() as $definition) {
            if ($definition['key'] === $taskKey) {
                return true;
            }
        }

        return false;
    }

    public function commandFor(string $taskKey): ?string
    {
        foreach ($this->coreTaskDefinitions() as $definition) {
            if ($definition['key'] === $taskKey) {
                return $definition['command'];
            }
        }

        foreach ($this->activeModuleTasks() as $task) {
            if ($task['key'] === $taskKey) {
                return $task['command'];
            }
        }

        return null;
    }

    /**
     * Source unique des tâches Core — sert à la fois à `register()` et à
     * `describeTasks()`. `cron` en expression brute (plutôt que
     * `everyMinute()`/`daily()` fluents) pour être exploitable telle quelle
     * par CronHumanizer et CronExpression.
     *
     * @return list<array{key: string, command: string, cron: string, description: string}>
     */
    private function coreTaskDefinitions(): array
    {
        return [
            [
                'key' => 'baobab.content.publish-due',
                'command' => ContentPublishDueCommand::class,
                'cron' => '* * * * *',
                'description' => 'Publie les contenus programmés arrivés à échéance.',
            ],
            [
                'key' => 'baobab.content.unpublish-due',
                'command' => ContentUnpublishDueCommand::class,
                'cron' => '* * * * *',
                'description' => 'Dépublie les contenus dont la date de fin est dépassée.',
            ],
            [
                'key' => 'baobab.content.purge-trash',
                'command' => ContentPurgeTrashCommand::class,
                'cron' => '0 0 * * *',
                'description' => 'Purge les contenus à la corbeille au-delà de la rétention.',
            ],
            [
                'key' => 'baobab.notifications.purge-old',
                'command' => NotificationsPurgeCommand::class,
                'cron' => '0 0 * * *',
                'description' => 'Purge les notifications anciennes au-delà de la rétention.',
            ],
            [
                'key' => 'baobab.media.purge-trash',
                'command' => MediaPurgeTrashCommand::class,
                'cron' => '0 0 * * *',
                'description' => 'Purge les médias à la corbeille au-delà de la rétention.',
            ],
            [
                'key' => 'baobab.seo.purge-404-log',
                'command' => NotFoundPurgeCommand::class,
                'cron' => '0 0 * * *',
                'description' => 'Purge le journal des pages 404 au-delà de la rétention.',
            ],
            [
                'key' => 'baobab.mail.purge-log',
                'command' => MailLogPurgeCommand::class,
                'cron' => '0 0 * * *',
                'description' => 'Purge le journal des e-mails au-delà de la rétention.',
            ],
            [
                'key' => 'baobab.forms.purge',
                'command' => FormSubmissionsPurgeCommand::class,
                'cron' => '0 0 * * *',
                'description' => 'Purge les soumissions de formulaires au-delà de leur rétention.',
            ],
            [
                'key' => 'baobab.privacy.purge-exports',
                'command' => PrivacyPurgeExportsCommand::class,
                'cron' => '0 0 * * *',
                'description' => "Détruit les archives d'export de données personnelles échues, et leur mot de passe non lu.",
            ],
            [
                'key' => 'baobab.privacy.run-due-erasures',
                'command' => PrivacyRunDueErasuresCommand::class,
                'cron' => '0 * * * *',
                'description' => 'Met en file les effacements de données personnelles arrivés à échéance.',
            ],
            [
                'key' => 'baobab.scheduler.purge-runs',
                'command' => SchedulerPurgeRunsCommand::class,
                'cron' => '0 0 * * *',
                'description' => "Purge l'historique d'exécution du scheduler au-delà de la rétention.",
            ],
            [
                'key' => 'baobab.audit.purge',
                'command' => AuditPurgeCommand::class,
                'cron' => '0 0 * * *',
                'description' => "Purge le journal d'audit au-delà de la rétention.",
            ],
            [
                'key' => 'baobab.backups.create',
                'command' => BackupRunCommand::class,
                'cron' => '0 3 * * *',
                'description' => 'Crée une sauvegarde complète (base + fichiers) — désactivable depuis admin/system/backups.',
            ],
            [
                'key' => 'baobab.health.check',
                'command' => HealthCheckCommand::class,
                'cron' => '*/5 * * * *',
                'description' => 'Rafraîchit les 9 contrôles de santé (admin/system/health).',
            ],
        ];
    }

    /**
     * Base pas forcément migrée à cet instant (installateur, tests, boot
     * anticipé) — même garde que
     * BaobabServiceProvider::bootstrapActiveModules().
     */
    private function registerModuleTasks(Schedule $schedule): void
    {
        try {
            $suspended = $this->suspendedTaskKeys();

            foreach ($this->activeModuleTasks() as $task) {
                if (in_array($task['key'], $suspended, true)) {
                    continue;
                }

                $this->record($schedule->command($task['command'])->cron($task['cron']), $task['key']);
            }
        } catch (Throwable) {
            // DB injoignable — les tâches Core restent enregistrées, celles des modules attendront le prochain boot.
        }
    }

    /**
     * @return list<array{key: string, command: string, cron: string, description: ?string, source: string, module_name: string}>
     */
    private function activeModuleTasks(): array
    {
        if (! Schema::hasTable('modules')) {
            return [];
        }

        $tasks = [];

        foreach (Module::where('status', 'active')->get() as $module) {
            foreach ($module->manifest['schedule'] ?? [] as $task) {
                $tasks[] = [
                    'key' => $task['key'],
                    'command' => $task['command'],
                    'cron' => $task['cron'],
                    'description' => $task['description'] ?? null,
                    'source' => 'module',
                    'module_name' => $module->name,
                ];
            }
        }

        return $tasks;
    }

    /**
     * Une expression cron d'un module reste non validée à la lecture du
     * manifeste (gap pré-existant, spec 12 §2.2, suivi n° 230) — un module
     * tiers mal écrit ne doit pas pour autant faire tomber tout l'écran.
     */
    private function nextRunDate(string $cron): ?DateTimeInterface
    {
        try {
            return (new CronExpression($cron))->getNextRunDate();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return list<string>
     */
    private function suspendedTaskKeys(): array
    {
        if (! Schema::hasTable('scheduled_task_suspensions')) {
            return [];
        }

        return array_values(ScheduledTaskSuspension::query()
            ->pluck('task_key')
            ->map(static fn (mixed $taskKey): string => (string) $taskKey)
            ->all());
    }

    private function record(Event $event, string $taskKey): void
    {
        $event
            ->before(fn () => $this->recordStart($taskKey))
            ->after(function () use ($event, $taskKey): void {
                $success = $event->exitCode === 0;

                $this->recordFinish(
                    $taskKey,
                    $success ? 'success' : 'failed',
                    $success ? null : "Code de sortie : {$event->exitCode}",
                );
            });
    }

    /**
     * Extraites en méthodes publiques (plutôt que des closures inline) pour
     * rester testables sans devoir exécuter un vrai processus planifié —
     * SchedulerRegistrarTest les appelle directement, RunScheduledTask
     * (M9 chantier 0.a Pass B) aussi pour l'exécution manuelle.
     */
    public function recordStart(string $taskKey): void
    {
        ScheduledTaskRun::create([
            'task_key' => $taskKey,
            'started_at' => now(),
            'status' => 'running',
        ]);

        Hook::action('baobab.schedule.task.starting', ['task_key' => $taskKey]);
    }

    public function recordFinish(string $taskKey, string $status, ?string $error = null): void
    {
        ScheduledTaskRun::where('task_key', $taskKey)
            ->where('status', 'running')
            ->latest('id')
            ->first()
            ?->update(['status' => $status, 'finished_at' => now(), 'error' => $error]);

        Hook::action('baobab.schedule.task.finished', ['task_key' => $taskKey, 'status' => $status]);
    }
}
