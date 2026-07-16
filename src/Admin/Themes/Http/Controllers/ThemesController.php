<?php

declare(strict_types=1);

namespace Baobab\Admin\Themes\Http\Controllers;

use Baobab\Modules\Models\Module;
use Baobab\Themes\Actions\ActivateTheme;
use Baobab\Themes\Actions\GeneratePreviewLink;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * Écran « Thèmes » (spec 03 §7, M6 point 2) — active/prévisualise des thèmes
 * déjà installés. L'installation elle-même (upload ZIP/Composer) reste le
 * cycle générique des modules (M1, CLI uniquement), hors périmètre ici.
 * Accès gouverné par `baobab.system.themes.manage` (routes/admin.php).
 */
final class ThemesController
{
    public function index(): View
    {
        return view('baobab::admin.themes.index', [
            'themes' => Module::where('type', 'theme')->orderBy('title')->get(),
        ]);
    }

    public function activate(Module $theme, ActivateTheme $action): RedirectResponse
    {
        $action($theme->name);

        session()->flash('toast', ['type' => 'success', 'message' => __('baobab::admin.themes.activated')]);

        return redirect()->route('admin.themes.index');
    }

    public function preview(Module $theme, GeneratePreviewLink $action): RedirectResponse
    {
        return redirect($action($theme));
    }
}
