<?php

declare(strict_types=1);

namespace Acme\ThemeChild\Providers;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\View as ViewFacade;
use Illuminate\Support\ServiceProvider;

/**
 * Provider de la fixture thème enfant — le seul de l'arsenal de fixtures à
 * porter une vraie logique, ce qui en fait le témoin du n° 125 (la préview
 * n'enregistrait le provider d'aucun thème, faute d'être active).
 *
 * Il exerce les deux moitiés du cycle en une seule marque observable :
 * `register()` fusionne la configuration d'où sort la valeur, `boot()` déclare
 * le view composer qui la pose dans la vue. Si `single.blade.php` affiche
 * `child-provider-ran`, les deux ont tourné.
 */
final class ThemeChildServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__, 2).'/config/theme.php', 'acme_theme_child');
    }

    public function boot(): void
    {
        ViewFacade::composer('theme::templates.single', function (View $view): void {
            $view->with('themeChildMark', (string) config('acme_theme_child.mark', ''));
        });
    }
}
