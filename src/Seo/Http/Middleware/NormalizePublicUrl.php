<?php

declare(strict_types=1);

namespace Baobab\Seo\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Impose une forme canonique d'URL publique (spec 07 §3 dernière puce) —
 * minuscules, trailing slash selon `baobab.redirects.trailing_slash`
 * ("strip" par défaut, "append" pour l'inverse). Une variante non conforme
 * redirige 301 vers la forme canonique plutôt que de répondre 200 sur les
 * deux — un seul format indexable. Câblé avant `ResolveRedirect` sur le
 * groupe de routes publiques : inutile de tester des redirections sur une
 * URL qui va de toute façon être normalisée d'abord.
 *
 * `Request::path()` trim déjà le trailing slash (et le leading) avant même
 * d'atteindre ce middleware — inutilisable pour détecter sa présence.
 * `getPathInfo()` (Symfony) rend le chemin brut, trailing slash compris.
 */
final class NormalizePublicUrl
{
    public function handle(Request $request, Closure $next): Response
    {
        $path = $request->getPathInfo();

        if ($path === '' || $path === '/') {
            return $next($request);
        }

        $canonical = $this->canonicalize($path);

        if ($canonical === $path) {
            return $next($request);
        }

        $query = $request->getQueryString();
        $url = $canonical.($query !== null ? "?{$query}" : '');

        return redirect($url, 301);
    }

    private function canonicalize(string $path): string
    {
        $path = mb_strtolower($path);

        if ((string) config('baobab.redirects.trailing_slash', 'strip') === 'strip') {
            return rtrim($path, '/') ?: '/';
        }

        return str_ends_with($path, '/') ? $path : "{$path}/";
    }
}
