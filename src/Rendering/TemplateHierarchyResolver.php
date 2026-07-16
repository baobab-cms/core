<?php

declare(strict_types=1);

namespace Baobab\Rendering;

use Illuminate\Support\Facades\View;
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
    /**
     * @param  list<string>  $candidates  Du plus spécifique au plus générique.
     */
    public function resolve(array $candidates): string
    {
        foreach ($candidates as $candidate) {
            if (View::exists("theme::templates.{$candidate}")) {
                return "theme::templates.{$candidate}";
            }

            if (View::exists("baobab::templates.{$candidate}")) {
                return "baobab::templates.{$candidate}";
            }
        }

        throw new RuntimeException('Aucun template résolu — au moins "index" doit exister dans les défauts du Core.');
    }
}
