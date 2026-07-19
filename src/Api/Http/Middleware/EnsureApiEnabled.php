<?php

declare(strict_types=1);

namespace Baobab\Api\Http\Middleware;

use Baobab\Api\Models\ApiSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Interrupteur global REST (spec 08 §4.3, Réglages > API) — désactivé, tout
 * `/api/v1/*` répond 404 (cohérent avec le reste du contrat REST : un type
 * inconnu répond déjà 404, pas un statut dédié). Le moins cher à vérifier
 * du groupe, posé en premier après le CORS.
 */
final class EnsureApiEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if(! ApiSetting::current()->rest_enabled, 404);

        return $next($request);
    }
}
