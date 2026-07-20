<?php

declare(strict_types=1);

namespace Baobab\Api\Http\Controllers;

use Illuminate\Contracts\View\View;

/**
 * Visionneuse OpenAPI (spec 08 §7, M7 point 4b Pass B) — page autonome
 * (jamais `layouts.admin`, Scalar fournit sa propre UI plein écran) qui
 * consomme `GET /api/v1/openapi.json` (Pass A) côté client.
 */
final class ApiDocsController
{
    public function show(): View
    {
        return view('baobab::api.docs');
    }
}
