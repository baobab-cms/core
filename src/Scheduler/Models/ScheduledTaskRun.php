<?php

declare(strict_types=1);

namespace Baobab\Scheduler\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Une exécution d'une tâche planifiée (spec 12 §2) — Core ou déclarée par un
 * module via `schedule` au manifeste. Écrite par SchedulerRegistrar.
 *
 * @property int $id
 * @property string $task_key
 * @property Carbon $started_at
 * @property Carbon|null $finished_at
 * @property string $status
 * @property string|null $error
 */
class ScheduledTaskRun extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'task_key',
        'started_at',
        'finished_at',
        'status',
        'error',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
