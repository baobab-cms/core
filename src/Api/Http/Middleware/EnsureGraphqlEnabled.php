<?php

declare(strict_types=1);

namespace Baobab\Api\Http\Middleware;

use Baobab\Api\Models\ApiSetting;
use Closure;
use GraphQL\Validator\Rules\DisableIntrospection;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Interrupteur GraphQL dédié (spec 08 §3.3/§4.3, Réglages > API), distinct
 * de `EnsureApiEnabled` (REST) — les deux familles sont indépendantes,
 * désactiver l'une ne désactive pas l'autre. Configure aussi
 * `lighthouse.security.disable_introspection` pour cette requête à partir
 * du même réglage : `ProvidesValidationRules`/`ProvidesCacheableValidationRules`
 * sont liés en `bind()` (jamais `singleton()`) par `LighthouseServiceProvider`
 * et relisent la config à chaque construction, donc un `Config::set()` ici,
 * avant que le contrôleur GraphQL de Lighthouse ne s'exécute, est repris tel
 * quel — pas besoin d'un second middleware pour un seul `ApiSetting::current()`.
 */
final class EnsureGraphqlEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        $setting = ApiSetting::current();

        abort_if(! $setting->graphql_enabled, 404);

        config(['lighthouse.security.disable_introspection' => $setting->graphql_introspection_enabled
            ? DisableIntrospection::DISABLED
            : DisableIntrospection::ENABLED,
        ]);

        return $next($request);
    }
}
