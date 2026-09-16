<?php

declare(strict_types=1);

namespace Baobab\Admin\System\Http\Controllers;

use Baobab\Scheduler\ScheduledTaskDescription;
use Baobab\Scheduler\SchedulerRegistrar;
use Baobab\System\Actions\RunScheduledTask;
use Baobab\System\Actions\ToggleScheduledTask;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use InvalidArgumentException;

/**
 * Écran `admin/system/scheduler` (spec 12 §2.3) — liste en lecture seule des
 * tâches Core et de module, avec « Exécuter maintenant » et « Suspendre/
 * réactiver » (module uniquement). Adaptateur mince, patron
 * `MaintenanceController` : aucune autorisation ici, tout au middleware
 * `can:` des routes ; les deux Actions (`RunScheduledTask`,
 * `ToggleScheduledTask`) portent la décision.
 */
final class SchedulerController
{
    public function index(SchedulerRegistrar $registrar): View
    {
        return view('baobab::admin.system.scheduler.index', [
            'tasks' => $registrar->describeTasks(),
            'columns' => $this->columns(),
        ]);
    }

    public function run(string $taskKey, RunScheduledTask $action): RedirectResponse
    {
        try {
            $success = $action($taskKey);
        } catch (InvalidArgumentException $exception) {
            session()->flash('toast', ['type' => 'error', 'message' => $exception->getMessage()]);

            return redirect()->route('admin.system.scheduler.index');
        }

        session()->flash('toast', [
            'type' => $success ? 'success' : 'error',
            'message' => $success ? __('baobab::admin.scheduler.run_triggered') : __('baobab::admin.scheduler.run_failed'),
        ]);

        return redirect()->route('admin.system.scheduler.index');
    }

    public function suspend(string $taskKey, ToggleScheduledTask $action): RedirectResponse
    {
        try {
            $action->suspend($taskKey);
        } catch (InvalidArgumentException $exception) {
            session()->flash('toast', ['type' => 'error', 'message' => $exception->getMessage()]);

            return redirect()->route('admin.system.scheduler.index');
        }

        session()->flash('toast', ['type' => 'success', 'message' => __('baobab::admin.scheduler.suspended')]);

        return redirect()->route('admin.system.scheduler.index');
    }

    public function resume(string $taskKey, ToggleScheduledTask $action): RedirectResponse
    {
        try {
            $action->resume($taskKey);
        } catch (InvalidArgumentException $exception) {
            session()->flash('toast', ['type' => 'error', 'message' => $exception->getMessage()]);

            return redirect()->route('admin.system.scheduler.index');
        }

        session()->flash('toast', ['type' => 'success', 'message' => __('baobab::admin.scheduler.resumed')]);

        return redirect()->route('admin.system.scheduler.index');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function columns(): array
    {
        return [
            [
                'key' => 'source',
                'label' => __('baobab::admin.scheduler.column_source'),
                'raw' => true,
                'render' => fn (ScheduledTaskDescription $task) => view('baobab::admin.system.scheduler.partials.source', ['task' => $task])->render(),
            ],
            [
                'key' => 'description',
                'label' => __('baobab::admin.scheduler.column_description'),
                'render' => fn (ScheduledTaskDescription $task) => $task->description ?? $task->command,
            ],
            [
                'key' => 'cronHuman',
                'label' => __('baobab::admin.scheduler.column_cron'),
                'render' => fn (ScheduledTaskDescription $task) => $task->cronHuman,
            ],
            [
                'key' => 'last_run',
                'label' => __('baobab::admin.scheduler.column_last_run'),
                'raw' => true,
                'render' => fn (ScheduledTaskDescription $task) => view('baobab::admin.system.scheduler.partials.last-run', ['task' => $task])->render(),
            ],
            [
                'key' => 'next_run',
                'label' => __('baobab::admin.scheduler.column_next_run'),
                'render' => fn (ScheduledTaskDescription $task) => $task->nextRun?->format('Y-m-d H:i') ?? '—',
            ],
            [
                'key' => 'actions',
                'label' => '',
                'raw' => true,
                'render' => fn (ScheduledTaskDescription $task) => view('baobab::admin.system.scheduler.partials.actions', ['task' => $task])->render(),
            ],
        ];
    }
}
