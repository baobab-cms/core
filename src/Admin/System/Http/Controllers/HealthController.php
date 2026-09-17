<?php

declare(strict_types=1);

namespace Baobab\Admin\System\Http\Controllers;

use Baobab\System\Actions\RunHealthChecks;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Spatie\Health\Facades\Health;

/**
 * Écran `admin/system/health` (spec 12 §7.1) — lecture du dernier relevé
 * stocké, rafraîchi par la tâche planifiée (§2) et « à la demande ».
 * Adaptateur mince, patron `QueuesController` : aucune autorisation ici,
 * tout au middleware `can:` des routes.
 */
final class HealthController
{
    public function index(): View
    {
        $results = Health::resultStores()->first()?->latestResults();

        return view('baobab::admin.system.health.index', [
            'results' => $results,
            'finishedAt' => $results?->finishedAt,
        ]);
    }

    public function refresh(RunHealthChecks $action): RedirectResponse
    {
        $results = $action();

        session()->flash('toast', [
            'type' => $results->allChecksOk() ? 'success' : 'error',
            'message' => __($results->allChecksOk() ? 'baobab::admin.health.refreshed_ok' : 'baobab::admin.health.refreshed_failing'),
        ]);

        return redirect()->route('admin.system.health.index');
    }
}
