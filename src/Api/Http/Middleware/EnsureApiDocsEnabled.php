<?php

declare(strict_types=1);

namespace Baobab\Api\Http\Middleware;

use Baobab\Api\Models\ApiSetting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Interrupteur dédié de `/api/docs` (spec 08 §7, M7 point 4b Pass B) —
 * « activée en local, sous permission en production » : `docs_enabled`
 * (patron `graphql_enabled`) gate d'abord globalement (404, jamais une
 * erreur d'autorisation) ; en production seulement, un acteur authentifié
 * sur le guard `baobab` doit en plus détenir `baobab.system.api.manage`
 * (même permission que l'écran de réglages, décidé avec l'utilisateur —
 * pas de permission dédiée).
 */
final class EnsureApiDocsEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        $setting = ApiSetting::current();

        abort_if(! $setting->docs_enabled, 404);

        if (app()->environment('production')) {
            abort_unless(Auth::guard('baobab')->user()?->can('baobab.system.api.manage') ?? false, 403);
        }

        return $next($request);
    }
}
