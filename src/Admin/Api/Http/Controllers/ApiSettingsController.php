<?php

declare(strict_types=1);

namespace Baobab\Admin\Api\Http\Controllers;

use Baobab\Api\Actions\UpdateApiSettings;
use Baobab\Api\Models\ApiSetting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Écran de réglages API (spec 08 §4.3, M7 point 2 Pass B). Accès gouverné
 * par la permission `baobab.system.api.manage` au niveau de la route
 * (routes/admin.php), patron exact `BrandingController`/`SeoSettingsController`.
 */
final class ApiSettingsController
{
    public function index(): View
    {
        return view('baobab::admin.api.index', [
            'setting' => ApiSetting::current(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'rest_enabled' => ['nullable', 'boolean'],
            'rate_limit_per_minute' => ['required', 'integer', 'min:1'],
            'allowed_origins' => ['nullable', 'string'],
            'graphql_enabled' => ['nullable', 'boolean'],
            'graphql_introspection_enabled' => ['nullable', 'boolean'],
            'docs_enabled' => ['nullable', 'boolean'],
        ]);

        $validated['rest_enabled'] = $request->boolean('rest_enabled');
        $validated['graphql_enabled'] = $request->boolean('graphql_enabled');
        $validated['graphql_introspection_enabled'] = $request->boolean('graphql_introspection_enabled');
        $validated['docs_enabled'] = $request->boolean('docs_enabled');

        app(UpdateApiSettings::class)($validated);

        session()->flash('toast', ['type' => 'success', 'message' => __('baobab::admin.api.updated')]);

        return redirect()->route('admin.api.index');
    }
}
