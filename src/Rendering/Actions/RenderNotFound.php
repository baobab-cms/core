<?php

declare(strict_types=1);

namespace Baobab\Rendering\Actions;

use Baobab\Rendering\TemplateHierarchyResolver;
use Illuminate\Http\Response;

/**
 * Rendu 404 (spec 03 §4, dernière ligne de la hiérarchie) — partagé par
 * RenderContentEntry, RenderContentArchive et le fallback de routage public
 * pour toute URL non résolue.
 */
final class RenderNotFound
{
    public function __construct(private readonly TemplateHierarchyResolver $hierarchy) {}

    public function __invoke(): Response
    {
        $view = $this->hierarchy->resolve(['404', 'index']);

        return response(view($view)->render(), 404);
    }
}
