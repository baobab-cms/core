<?php

declare(strict_types=1);

namespace Baobab\System\HealthChecks;

use Baobab\Scheduler\Models\ScheduledTaskRun;
use Illuminate\Support\Carbon;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

/**
 * Contrôle « Scheduler » (spec 12 §7.2 : vu il y a < 2 min, silence > 5
 * min) — aucun heartbeat dédié : `baobab.content.publish-due`/
 * `.unpublish-due` tournent déjà chaque minute (`SchedulerRegistrar`,
 * cron `* * * * *`) et journalisent dans `scheduled_task_runs` à chaque
 * exécution ; la ligne la plus récente, tous types de tâches confondus,
 * sert directement de heartbeat (décision de séance, cadrage Pass E) —
 * aucune infrastructure neuve, contrairement au mécanisme propre à
 * `spatie/laravel-health` (`ScheduleCheck` + heartbeat dédié).
 */
final class SchedulerCheck extends Check
{
    public function run(): Result
    {
        $result = Result::make();

        $latest = ScheduledTaskRun::max('started_at');

        if ($latest === null) {
            return $result->warning('Aucune exécution planifiée enregistrée pour le moment.');
        }

        $minutes = Carbon::parse($latest)->diffInMinutes(now());
        $result->shortSummary($minutes.' min');

        if ($minutes > 5) {
            return $result->failed("Aucune exécution planifiée vue depuis {$minutes} minutes.");
        }

        if ($minutes >= 2) {
            return $result->warning("Dernière exécution planifiée il y a {$minutes} minutes.");
        }

        return $result->ok();
    }
}
