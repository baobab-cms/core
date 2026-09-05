<?php

declare(strict_types=1);

namespace Baobab\Admin\Demo\Http\Controllers;

use Baobab\Demo\Actions\RemoveDemoContent;
use Baobab\Demo\Models\DemoContent;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * Écran « Contenu de démonstration » (spec 03 §7, M8 point 3, Pass D3) —
 * adaptateur mince : aucune Action écrite ici, `RemoveDemoContent` (Pass D2b)
 * fait tout le travail, l'écran ne fait que le déclencher.
 *
 * Écran dédié plutôt qu'un onglet des écrans Thèmes ou Modules — décision du
 * 4 septembre 2026 avec l'utilisateur (suivi n° 247) : ni l'un ni l'autre
 * n'a de lien réel avec ce que fait cet écran, et le Core suit déjà ce
 * patron pour chaque domaine (`ReadingSettingsController`, `BrandingController`).
 *
 * Accès gouverné par `baobab.system.demo_content.manage` (routes/admin.php).
 */
final class DemoContentController
{
    public function index(): View
    {
        return view('baobab::admin.demo-content.index', [
            'present' => DemoContent::isPresent(),
        ]);
    }

    public function destroy(RemoveDemoContent $action): RedirectResponse
    {
        $lines = $action();

        session()->flash('toast', [
            'type' => 'success',
            'message' => implode(' ', $lines),
        ]);

        return redirect()->route('admin.demo-content.index');
    }
}
