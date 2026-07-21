<?php

declare(strict_types=1);

namespace Baobab\Rendering\Actions;

use Baobab\Rendering\TemplateHierarchyResolver;
use Baobab\Seo\Actions\ComposeSeoMeta;
use Baobab\Seo\SeoContext;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Page de résultats de recherche du site public (spec 11 §4.2) — résout le
 * volet « recherche » différé du pipeline de rendu M6 (suivi n° 49).
 * Hiérarchie `search → index` (spec 03 §4) : un thème fournit
 * `templates/search.blade.php` ou hérite du template générique. Données :
 * `results` (groupes par source, via `baobab_search()` — le helper que le
 * thème utiliserait lui-même, exactement le même chemin de code) et `query`.
 * Pas de filtre `baobab.render.data` : pas de Content Type/entrée concret
 * ici (patron `RenderHomepage::renderDefault()`/`RenderNotFound`), le
 * post-traitement des résultats a son propre hook (`baobab.search.results`,
 * appliqué par `RunSearch`).
 */
final class RenderSearchPage
{
    public function __construct(
        private readonly TemplateHierarchyResolver $hierarchy,
        private readonly ComposeSeoMeta $seo,
        private readonly SeoContext $seoContext,
    ) {}

    public function __invoke(Request $request): Response
    {
        $term = trim((string) $request->query('q'));

        $this->seoContext->set(($this->seo)(null, null));

        $view = $this->hierarchy->resolve(['search', 'index']);

        return response()->view($view, [
            'query' => $term,
            'results' => mb_strlen($term) >= 2 ? baobab_search($term) : [],
        ]);
    }
}
