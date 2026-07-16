<?php

declare(strict_types=1);

namespace Baobab\Themes\Http\Middleware;

use Baobab\Modules\Models\Module;
use Baobab\Rendering\ActiveThemeResolver;
use Baobab\Themes\ThemeViewRegistrar;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Point d'enregistrement unique (par requête) du thème dont les vues
 * doivent être servies (spec 03 §7 préview) : le thème en préview pour la
 * session courante (`baobab.preview_theme_id`, jamais visible des autres
 * visiteurs) s'il y en a un, sinon le thème réellement actif. Câblé sur le
 * groupe de routes publiques (PublicRouteRegistrar) — les vues admin
 * n'utilisent jamais l'espace `theme::`.
 */
final class ResolveActiveTheme
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var int|null $previewId */
        $previewId = session('baobab.preview_theme_id');

        $theme = $previewId !== null
            ? Module::where('id', $previewId)->where('type', 'theme')->first()
            : app(ActiveThemeResolver::class)->current();

        app(ThemeViewRegistrar::class)->registerFor($theme);

        return $next($request);
    }
}
