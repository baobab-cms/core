<?php

declare(strict_types=1);

namespace Baobab\Scheduler;

/**
 * Les trois états d'une ligne `scheduled_task_runs` (spec 12 §2.3 : `running`
 * → `success` | `failed`), écrits par SchedulerRegistrar. Catalogue fermé —
 * enum plutôt que chaîne libre, patron `MailLogStatus`. Libellé et variante
 * de pastille vivent ici et non dans la vue admin/system/scheduler, même
 * raison que `MailLogStatus` (suivi n° 138).
 */
enum ScheduledTaskRunStatus: string
{
    case Running = 'running';
    case Success = 'success';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Running => __('baobab::admin.scheduler.status_running'),
            self::Success => __('baobab::admin.scheduler.status_success'),
            self::Failed => __('baobab::admin.scheduler.status_failed'),
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Success => 'success',
            self::Failed => 'danger',
            self::Running => 'warning',
        };
    }
}
