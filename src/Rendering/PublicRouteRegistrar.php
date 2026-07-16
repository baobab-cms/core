<?php

declare(strict_types=1);

namespace Baobab\Rendering;

use Baobab\Rendering\Actions\RenderContentArchive;
use Baobab\Rendering\Actions\RenderContentEntry;
use Baobab\Rendering\Actions\RenderNotFound;
use Baobab\Rendering\Actions\ResolveAddressableContentType;
use Baobab\Themes\Http\Middleware\ResolveActiveTheme;
use Illuminate\Routing\Router;

/**
 * Routes publiques des types adressables (spec 03 §3, spec 02 §4.3) — une
 * route générique `/{prefix}/{slug?}`, résolue à la requête via
 * ResolveAddressableContentType plutôt qu'une route statique par type : un
 * Content Type nouvellement créé/activé devient routable immédiatement, sans
 * attendre un redémarrage de l'application (contrairement à une boucle
 * d'enregistrement au boot). `slug` absent = archive, présent = détail. Un
 * fallback couvre le reste des URLs publiques non résolues (§4, « 404 »).
 * `ResolveActiveTheme` (M6 point 2) enregistre les vues du thème actif/en
 * préview une fois par requête sur ce même groupe, avant la résolution de
 * template (M6 point 1).
 */
final class PublicRouteRegistrar
{
    public function register(Router $router): void
    {
        $router->middleware(['web', ResolveActiveTheme::class])->group(function () use ($router): void {
            $router->get('/{prefix}/{slug?}', function (string $prefix, ?string $slug = null) {
                if ($prefix === (string) config('baobab.admin.path', 'admin')) {
                    abort(404);
                }

                $contentType = app(ResolveAddressableContentType::class)($prefix);

                if ($contentType === null) {
                    return app(RenderNotFound::class)();
                }

                return $slug === null
                    ? app(RenderContentArchive::class)($contentType)
                    : app(RenderContentEntry::class)($contentType, $slug);
            })->where('prefix', '[^/]+')->where('slug', '[^/]+')->name('baobab.public.show');

            $router->fallback(function () {
                // Une URL /admin/* non résolue reste une 404 Laravel classique —
                // ce n'est pas au thème public de rendre l'espace d'administration.
                if (str_starts_with(request()->path(), (string) config('baobab.admin.path', 'admin'))) {
                    abort(404);
                }

                return app(RenderNotFound::class)();
            });
        });
    }
}
