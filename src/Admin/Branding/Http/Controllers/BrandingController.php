<?php

declare(strict_types=1);

namespace Baobab\Admin\Branding\Http\Controllers;

use Baobab\Branding\Actions\UpdateBrandingSettings;
use Baobab\Branding\Models\BrandingSetting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Écran de réglages de marque (spec-admin.md §11.1, « version simple en v1 ») :
 * logo, favicon admin, couleur primaire. Accès gouverné par la permission
 * `baobab.system.branding.manage` au niveau de la route (routes/admin.php).
 */
final class BrandingController
{
    public function index(): View
    {
        return view('baobab::admin.branding.index', [
            'setting' => BrandingSetting::current()->load(['logo', 'favicon']),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'logo_media_id' => ['nullable', 'integer', 'exists:media,id'],
            'favicon_media_id' => ['nullable', 'integer', 'exists:media,id'],
            'primary_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        app(UpdateBrandingSettings::class)($validated);

        session()->flash('toast', ['type' => 'success', 'message' => __('baobab::admin.branding.updated')]);

        return redirect()->route('admin.branding.index');
    }
}
