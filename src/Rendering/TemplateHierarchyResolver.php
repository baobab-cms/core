<?php

declare(strict_types=1);

namespace Baobab\Rendering;

use Illuminate\Contracts\View\Factory;
use RuntimeException;

/**
 * Résolution générique de la hiérarchie de templates (spec 03 §4) : pour
 * chaque candidat, cherche d'abord dans le thème actif (`theme::`, enregistré
 * par ActiveThemeResolver si un thème est actif) puis dans les défauts du
 * Core (`baobab::`). Le thème parent (spec 03 §6, M6 point 2) s'insérera
 * plus tard entre les deux sans changer cette API.
 */
final class TemplateHierarchyResolver
{
    public function __construct(private readonly Factory $views) {}

    /**
     * @param  list<string>  $candidates  Du plus spécifique au plus générique.
     * @return view-string
     */
    public function resolve(array $candidates): string
    {
        foreach ($candidates as $candidate) {
            // Factory::exists() porte `@phpstan-assert-if-true view-string $view`
            // (stub Larastan) : dans cette branche, le type de $themeView/
            // $coreView est affiné en view-string — appelé sur l'instance du
            // contrat (pas la façade statique View::, dont la résolution ne
            // propage pas fiablement cette assertion).
            $themeView = "theme::templates.{$candidate}";

            if ($this->views->exists($themeView)) {
                return $themeView;
            }

            $coreView = "baobab::templates.{$candidate}";

            if ($this->views->exists($coreView)) {
                return $coreView;
            }
        }

        throw new RuntimeException('Aucun template résolu — au moins "index" doit exister dans les défauts du Core.');
    }
}
