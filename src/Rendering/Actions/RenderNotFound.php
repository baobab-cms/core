<?php

declare(strict_types=1);

namespace Baobab\Rendering\Actions;

use Baobab\Rendering\TemplateHierarchyResolver;
use Baobab\Seo\Models\NotFoundHit;
use Illuminate\Http\Response;

/**
 * Rendu 404 (spec 03 §4, dernière ligne de la hiérarchie) — partagé par
 * RenderContentEntry, RenderContentArchive et le fallback de routage public
 * pour toute URL non résolue : point de passage unique de tous les 404
 * publics, donc seul endroit nécessaire pour alimenter le journal des 404
 * (spec 07 §4) sans duplication.
 */
final class RenderNotFound
{
    public function __construct(private readonly TemplateHierarchyResolver $hierarchy) {}

    public function __invoke(): Response
    {
        NotFoundHit::recordHit(request()->path(), request()->header('referer'));

        $view = $this->hierarchy->resolve(['404', 'index']);

        return response(view($view)->render(), 404);
    }
}
