<?php

declare(strict_types=1);

namespace Baobab\Seo\Http\Middleware;

use Baobab\Seo\Models\SeoSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Filet de sécurité indépendant de la balise `<meta name="robots">` (déjà
 * fusionnée dans `ComposeSeoMeta`, spec 07 §6/§8) : pose l'en-tête HTTP
 * `X-Robots-Tag: noindex` sur toute réponse publique hors production, sauf
 * dérogation explicite (`seo_settings.force_index_on_staging`) — utile
 * aussi pour une réponse non HTML (sitemap, robots.txt eux-mêmes) que la
 * balise meta ne peut pas couvrir.
 *
 * **`$next($request)` s'exécute d'abord**, donc `RedirectToInstaller` a déjà
 * calculé sa redirection avant qu'on lise `seo_settings` — mais la base peut
 * être hors d'atteinte, et une lecture non gardée après coup écrasait cette
 * redirection par une 500. *Trouvé le 3 septembre 2026, sur une archive
 * fraîchement décompressée* : masqué à la toute première requête (avant que
 * `.env` existe, `APP_ENV` vaut le repli `production` de Laravel, la
 * condition ci-dessous ne s'évalue donc jamais), le défaut frappait ensuite
 * **toute** visite suivante — la première requête écrit `.env`, `APP_ENV=local`
 * s'applique, et `SeoSetting::current()` retrouve une base qui n'existe pas
 * encore. Même patron que `ResolveRedirect` (n° 224) : mieux vaut servir la
 * réponse déjà calculée que la perdre pour un en-tête. Repli sur `noindex`
 * plutôt que sur l'inverse — cette garde protège justement contre la fuite
 * d'un environnement hors production dans les moteurs de recherche, et ne
 * pas savoir si l'exception existe n'est pas une raison de l'accorder.
 */
final class ForceStagingNoindexHeader
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! app()->environment('production') && ! $this->indexingAllowedOnStaging()) {
            $response->headers->set('X-Robots-Tag', 'noindex');
        }

        return $response;
    }

    private function indexingAllowedOnStaging(): bool
    {
        try {
            return SeoSetting::current()->force_index_on_staging;
        } catch (Throwable) {
            return false;
        }
    }
}
