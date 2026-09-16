<?php

declare(strict_types=1);

namespace Baobab\Scheduler\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Une tâche de module suspendue (spec 12 §2.3, M9 chantier 0.a Pass B) — la
 * présence d'une ligne vaut suspension. Écrite par
 * Baobab\System\Actions\ToggleScheduledTask, lue par
 * Baobab\Scheduler\SchedulerRegistrar.
 *
 * @property int $id
 * @property string $task_key
 */
class ScheduledTaskSuspension extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'task_key',
    ];
}
