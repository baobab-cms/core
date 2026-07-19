<?php

declare(strict_types=1);

namespace Baobab\Api\Http\Middleware;

use Baobab\Api\Models\ApiSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * CORS dédié à `/api/v1/*` (spec 08 §4.3, Réglages > API) — pas le
 * `config('cors.*')` global de Laravel : les origines autorisées vivent en
 * base (`ApiSetting`), lues à l'exécution comme n'importe quelle requête,
 * jamais au `boot()` (même principe déjà posé en Pass A pour éviter une
 * lecture DB au bootstrap). Répond directement au préflight `OPTIONS`
 * (jamais transmis au routeur/contrôleur). Vise les clients tiers Bearer
 * cross-origin — jamais `Access-Control-Allow-Credentials` : le mode SPA
 * same-origin est déjà couvert par `EnsureFrontendRequestsAreStateful`,
 * sans rapport avec cette CORS. Défaut : liste vide, aucune origine
 * externe (spec 08 §4.3) — l'en-tête n'est alors jamais posé, laissant le
 * navigateur bloquer la requête cross-origin comme d'habitude.
 */
final class HandleApiCors
{
    public function handle(Request $request, Closure $next): Response
    {
        $origin = $request->headers->get('Origin');
        $allowed = $origin !== null && in_array($origin, ApiSetting::current()->allowedOrigins(), true);

        $response = $request->getMethod() === 'OPTIONS' ? response('', 204) : $next($request);

        if ($allowed) {
            $response->headers->set('Access-Control-Allow-Origin', (string) $origin);
            $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PATCH, DELETE, OPTIONS');
            $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, Accept');
            $response->headers->set('Vary', 'Origin');
        }

        return $response;
    }
}
