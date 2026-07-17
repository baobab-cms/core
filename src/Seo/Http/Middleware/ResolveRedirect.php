<?php

declare(strict_types=1);

namespace Baobab\Seo\Http\Middleware;

use Baobab\Seo\Actions\ResolveRedirectTarget;
use Baobab\Seo\Models\Redirect;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Symfony\Component\HttpFoundation\Response;

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
