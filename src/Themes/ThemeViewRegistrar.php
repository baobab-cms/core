<?php

declare(strict_types=1);

namespace Baobab\Themes;

use Baobab\Modules\Models\Module;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\View;

/**
 * Enregistre les vues du thème actif (spec 03 §5-6) : espace de vues
 * `theme::` (consommé par TemplateHierarchyResolver, M6 point 1) et
 * surcharge des vues de modules — le mécanisme natif Laravel de vendor
 * override (`loadViewsFrom()` vérifie déjà `{config('view.paths')}/vendor/{namespace}`
 * avant le chemin du package) fait tout le travail dès lors que le chemin du
 * thème apparaît dans `config('view.paths')`, aucun résolveur custom
 * nécessaire pour ce volet. Cascade enfant → parent (spec 03 §6, un seul
 * niveau) : `theme::` porte les deux chemins comme hints multiples, résolus
 * dans l'ordre d'ajout par le View Finder de Laravel.
 *
 * Doit être appelé **par requête** (ResolveActiveTheme), jamais au `boot()`
 * d'un service provider : `Session` n'y est pas encore disponible (préview).
 * `replaceNamespace()` (pas `addNamespace()`, qui fusionne) et le
 * remplacement de `config('view.paths')` à partir d'une base capturée une
 * seule fois évitent qu'un thème enregistré à une requête « colle » à la
 * suivante — significatif dès qu'un même process traite plusieurs requêtes
 * (tests, Octane), pas seulement en PHP-FPM classique où chaque requête
 * repart d'un conteneur neuf.
 */
final class ThemeViewRegistrar
{
    public function __construct(private readonly Application $app) {}

    public function registerFor(?Module $theme): void
    {
        $paths = $theme !== null ? $this->paths($theme) : [];

        View::replaceNamespace('theme', $paths);

        config(['view.paths' => array_merge($paths, $this->baseViewPaths())]);

        // FileViewFinder mémoïse chaque "namespace::vue" déjà résolue,
        // indépendamment des hints actifs au moment de la résolution —
        // sans purge, un changement de thème (préview) resterait invisible
        // pour toute vue déjà résolue une première fois dans ce process
        // (significatif dès qu'un même process traite plusieurs requêtes :
        // tests, Octane — jamais un souci en PHP-FPM classique).
        View::getFinder()->flush();
    }

    /**
     * @return list<string>
     */
    private function paths(Module $theme): array
    {
        $paths = [$theme->path.'/resources/views'];

        /** @var string|null $parentName */
        $parentName = $theme->manifest['theme']['parent'] ?? null;

        if ($parentName !== null) {
            $parent = Module::where('name', $parentName)->where('type', 'theme')->first();

            if ($parent instanceof Module) {
                $paths[] = $parent->path.'/resources/views';
            }
        }

        return $paths;
    }

    /**
     * `config('view.paths')` tel qu'il existait avant toute injection de
     * chemin de thème — capturé une seule fois par cycle de vie de
     * l'application (singleton conteneur), pour que des appels répétés
     * remplacent plutôt qu'accumulent.
     *
     * @return list<string>
     */
    private function baseViewPaths(): array
    {
        if (! $this->app->bound('baobab.rendering.base_view_paths')) {
            $this->app->instance('baobab.rendering.base_view_paths', config('view.paths', []));
        }

        /** @var list<string> $paths */
        $paths = $this->app->make('baobab.rendering.base_view_paths');

        return $paths;
    }
}
