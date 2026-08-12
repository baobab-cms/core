<?php

declare(strict_types=1);

namespace Baobab\Admin\Themes\Http\Controllers;

use Baobab\Modules\Models\Module;
use Baobab\Themes\Actions\ActivateTheme;
use Baobab\Themes\Actions\DeactivateTheme;
use Baobab\Themes\Actions\GeneratePreviewLink;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * Écran « Thèmes » (spec 03 §7, M6 point 2) — active, désactive et
 * prévisualise des thèmes déjà installés. L'installation elle-même (upload
 * ZIP ou Composer) reste le cycle générique des modules, désormais accessible
 * depuis l'écran Modules (M8 point 9), hors périmètre ici. Accès gouverné par
 * `baobab.system.themes.manage` (routes/admin.php).
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

    /**
     * Ramène le site au rendu de repli du Core (spec 19 §6.1) — le seul
     * chemin par lequel l'état « aucun thème actif » se pilote depuis
     * l'interface, `ActivateTheme` désactivant toujours l'ancien thème au
     * profit d'un nouveau (n° 126). L'écran Modules ne peut pas y suppléer :
     * `ModuleLifecyclePresenter::canDeactivate()` exclut les thèmes.
     */
    public function deactivate(Module $theme, DeactivateTheme $action): RedirectResponse
    {
        $action($theme->name);

        session()->flash('toast', ['type' => 'success', 'message' => __('baobab::admin.themes.deactivated')]);

        return redirect()->route('admin.themes.index');
    }

    public function preview(Module $theme, GeneratePreviewLink $action): RedirectResponse
    {
        return redirect($action($theme));
    }
}
