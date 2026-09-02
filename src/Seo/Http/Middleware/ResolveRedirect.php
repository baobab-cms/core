<?php

declare(strict_types=1);

namespace Baobab\Seo\Http\Middleware;

use Baobab\Seo\Actions\ResolveRedirectTarget;
use Baobab\Seo\Models\Redirect;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Court-circuite le routage public si le chemin courant correspond à une
 * redirection active (spec 07 §4) — exécuté avant `ResolveActiveTheme` :
 * inutile de résoudre un thème pour une requête qui va être redirigée.
 * `status_code` 410 répond « gone » directement, `target` jamais consulté.
 */
final class ResolveRedirect
{
    public function handle(Request $request, Closure $next): Response
    {
        // **Rien à résoudre tant que la table n'existe pas.** Sur une archive
        // fraîchement décompressée, il n'y a pas encore de base : ce middleware
        // tourne pourtant sur **toute** requête publique, et sa requête faisait
        // finir chacune en 500 — c'est l'erreur que voyait l'utilisateur avant
        // même d'atteindre l'installateur (recette du 28 août 2026, n° 224).
        //
        // La garde porte sur la **table** et non sur le lock d'installation :
        // le lock atteste une installation terminée, quand ce qui manque ici
        // est seulement de quoi lire. C'est le même patron que
        // `BaobabServiceProvider::bootstrapActiveModules()`, et il vaut aussi
        // pour un site dont la base est momentanément injoignable — mieux vaut
        // servir la page sans résoudre de redirection que ne rien servir.
        try {
            if (! Schema::hasTable('redirects')) {
                return $next($request);
            }
        } catch (Throwable) {
            return $next($request);
        }

        $path = '/'.ltrim($request->path(), '/');
        $match = app(ResolveRedirectTarget::class)($path);

        if ($match === null) {
            return $next($request);
        }

        Redirect::whereKey($match['id'])->increment('hit_count', 1, ['last_hit_at' => now()]);

        if ($match['status_code'] === 410) {
            abort(410);
        }

        // redirect($to, $status) (helper global) est typé RedirectResponse|
        // Redirector par le stub Larastan (le cas sans argument renvoie un
        // Redirector, qui n'étend pas Response) — Redirector::to() a un
        // type de retour non ambigu. `new RedirectResponse(...)` directe
        // aurait été plus simple mais n'absolutise pas l'URL (nécessaire :
        // `target` est stocké relatif en base, comme `source`).
        return app(Redirector::class)->to($match['target'], $match['status_code']);
    }
}
