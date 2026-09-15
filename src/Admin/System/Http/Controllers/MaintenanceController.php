<?php

declare(strict_types=1);

namespace Baobab\Admin\System\Http\Controllers;

use Baobab\System\Actions\ToggleMaintenanceMode;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Écran de bascule du mode maintenance (spec 12 §6.1). Le secret de
 * contournement n'est jamais relisible après coup — flashé en session à
 * l'activation, jamais recalculé depuis `maintenanceMode()->data()` sur un
 * rechargement ultérieur.
 */
final class MaintenanceController
{
    public function index(Application $app): View
    {
        $active = $app->maintenanceMode()->active();

        return view('baobab::admin.system.maintenance.index', [
            'active' => $active,
            'data' => $active ? $app->maintenanceMode()->data() : null,
            'secret' => session('maintenance_secret'),
        ]);
    }

    public function activate(Request $request, ToggleMaintenanceMode $action): RedirectResponse
    {
        $validated = $request->validate([
            'retry' => ['nullable', 'integer', 'min:1'],
            'redirect' => ['nullable', 'string', 'max:255'],
            'generate_secret' => ['nullable', 'boolean'],
        ]);

        $secret = $action->activate([
            'retry' => $validated['retry'] ?? null,
            'redirect' => $validated['redirect'] ?? null,
            'withSecret' => (bool) ($validated['generate_secret'] ?? false),
        ]);

        session()->flash('maintenance_secret', $secret);
        session()->flash('toast', ['type' => 'success', 'message' => __('baobab::admin.maintenance.activated')]);

        return redirect()->route('admin.system.maintenance.index');
    }

    public function deactivate(ToggleMaintenanceMode $action): RedirectResponse
    {
        $action->deactivate();

        session()->flash('toast', ['type' => 'success', 'message' => __('baobab::admin.maintenance.deactivated')]);

        return redirect()->route('admin.system.maintenance.index');
    }
}
