<?php

declare(strict_types=1);

namespace Baobab\Admin\System\Http\Controllers;

use Baobab\Queue\Models\FailedJob;
use Baobab\System\Actions\DeleteFailedJobs;
use Baobab\System\Actions\RetryFailedJobs;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Écran `admin/system/queues` (spec 12 §3.2) — état des queues, jobs
 * échoués, relance/suppression unitaire et en masse. Adaptateur mince,
 * patron `SchedulerController` : aucune autorisation ici, tout au
 * middleware `can:` des routes.
 */
final class QueuesController
{
    public function index(): View
    {
        $failedJobs = FailedJob::query()->orderByDesc('failed_at')->paginate(20)->withQueryString();

        return view('baobab::admin.system.queues.index', [
            'failedJobs' => $failedJobs,
            'columns' => $this->columns(),
            'queueStats' => $this->queueStats(),
            'staleWorker' => $this->staleWorkerMinutes(),
        ]);
    }

    public function retry(FailedJob $failedJob, RetryFailedJobs $action): RedirectResponse
    {
        $action([$failedJob->uuid]);

        session()->flash('toast', ['type' => 'success', 'message' => __('baobab::admin.queues.retried')]);

        return redirect()->route('admin.system.queues.index');
    }

    public function delete(FailedJob $failedJob, DeleteFailedJobs $action): RedirectResponse
    {
        $action([$failedJob->uuid]);

        session()->flash('toast', ['type' => 'success', 'message' => __('baobab::admin.queues.deleted')]);

        return redirect()->route('admin.system.queues.index');
    }

    public function bulkRetry(Request $request, RetryFailedJobs $action): RedirectResponse
    {
        $action($this->uuidsFromRequest($request));

        session()->flash('toast', ['type' => 'success', 'message' => __('baobab::admin.queues.retried')]);

        return redirect()->route('admin.system.queues.index');
    }

    public function bulkDelete(Request $request, DeleteFailedJobs $action): RedirectResponse
    {
        $action($this->uuidsFromRequest($request));

        session()->flash('toast', ['type' => 'success', 'message' => __('baobab::admin.queues.deleted')]);

        return redirect()->route('admin.system.queues.index');
    }

    /**
     * @return list<string>
     */
    private function uuidsFromRequest(Request $request): array
    {
        return array_values(array_map('strval', (array) $request->input('ids', [])));
    }

    /**
     * Compte + âge du plus ancien job en attente, par queue —
     * `jobs.available_at` est un entier Unix (pas une colonne `timestamp`),
     * d'où l'écart calculé en PHP plutôt qu'en SQL.
     *
     * @return list<array{queue: string, pending: int, oldest_available_at: ?int}>
     */
    private function queueStats(): array
    {
        return array_values(DB::table('jobs')
            ->select('queue', DB::raw('COUNT(*) as pending'), DB::raw('MIN(available_at) as oldest_available_at'))
            ->groupBy('queue')
            ->get()
            ->map(fn ($row) => [
                'queue' => (string) $row->queue,
                'pending' => (int) $row->pending,
                'oldest_available_at' => $row->oldest_available_at !== null ? (int) $row->oldest_available_at : null,
            ])
            ->all());
    }

    /**
     * Minutes écoulées depuis la mise en attente du plus ancien job toutes
     * queues confondues, si elles dépassent le seuil configuré — `null` sinon
     * (aucune file en attente, ou worker actif). Détection autonome,
     * indépendante du futur tableau de bord santé (spec §7, Pass E).
     */
    private function staleWorkerMinutes(): ?int
    {
        $oldestAvailableAt = DB::table('jobs')->min('available_at');

        if ($oldestAvailableAt === null) {
            return null;
        }

        $minutes = (int) floor((now()->getTimestamp() - (int) $oldestAvailableAt) / 60);

        return $minutes >= (int) config('baobab.queues.stale_worker_minutes', 5) ? $minutes : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function columns(): array
    {
        return [
            [
                'key' => 'failed_at',
                'label' => __('baobab::admin.queues.column_date'),
                'render' => fn (FailedJob $job) => $job->failed_at->format('Y-m-d H:i'),
            ],
            [
                'key' => 'queue',
                'label' => __('baobab::admin.queues.column_queue'),
            ],
            [
                'key' => 'job',
                'label' => __('baobab::admin.queues.column_job'),
                'render' => fn (FailedJob $job) => $job->jobName(),
            ],
            [
                'key' => 'exception',
                'label' => __('baobab::admin.queues.column_exception'),
                'render' => fn (FailedJob $job) => $job->exceptionSummary(),
            ],
            [
                'key' => 'actions',
                'label' => '',
                'raw' => true,
                'render' => fn (FailedJob $job) => view('baobab::admin.system.queues.partials.failed-job-actions', ['job' => $job])->render(),
            ],
        ];
    }
}
