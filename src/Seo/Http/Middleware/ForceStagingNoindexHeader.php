<?php

declare(strict_types=1);

namespace Baobab\Seo\Http\Middleware;

use Baobab\Seo\Models\SeoSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Filet de sécurité indépendant de la balise `<meta name="robots">` (déjà
 * fusionnée dans `ComposeSeoMeta`, spec 07 §6/§8) : pose l'en-tête HTTP
 * `X-Robots-Tag: noindex` sur toute réponse publique hors production, sauf
 * dérogation explicite (`seo_settings.force_index_on_staging`) — utile
 * aussi pour une réponse non HTML (sitemap, robots.txt eux-mêmes) que la
 * balise meta ne peut pas couvrir.
 */
final class ForceStagingNoindexHeader
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! app()->environment('production') && ! SeoSetting::current()->force_index_on_staging) {
            $response->headers->set('X-Robots-Tag', 'noindex');
        }

        return $response;
    }
}
