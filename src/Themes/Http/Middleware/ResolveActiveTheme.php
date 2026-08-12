<?php

declare(strict_types=1);

namespace Baobab\Themes\Http\Middleware;

use Baobab\Modules\Models\Module;
use Baobab\Modules\ModuleAutoloader;
use Baobab\Rendering\ActiveThemeResolver;
use Baobab\Rendering\RenderedTheme;
use Baobab\Themes\Actions\PublishThemeAssets;
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
 *
 * Le thème retenu est **publié** (`RenderedTheme`) avant tout rendu : les
 * composants du Core qui en dépendent, `<x-baobab::vite>` en tête, doivent
 * lire la même décision que le registrar de vues. Sans cela un thème en
 * préview sortait avec ses gabarits mais la feuille de style d'un autre.
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

        if ($previewId !== null && $theme instanceof Module) {
            $this->prepareInactiveTheme($theme);
        }

        app(RenderedTheme::class)->decide($theme);
        app(ThemeViewRegistrar::class)->registerFor($theme);

        return $next($request);
    }

    /**
     * Un thème prévisualisé est, par définition, **inactif** : rien de ce que
     * l'activation lui apporte ne lui a été apporté. Deux manques en
     * découlaient, tous deux visibles sur le thème par défaut (n° 125).
     *
     * **Son provider n'était enregistré par personne.** `bootstrapActiveModules()`
     * ne parcourt que `status = 'active'`, et il tourne de toute façon au
     * `boot()` du provider du Core, où la session — seule à savoir quel thème
     * est en préview — n'existe pas encore. Les vues étaient donc montées sans
     * les view composers, les traductions ni le `config/theme.php` du thème,
     * ce qui suffisait à faire planter le rendu.
     *
     * **Ses assets n'étaient publiés nulle part.** `public/themes/{slug}` est
     * une jonction posée par l'activation ; sans elle le manifeste Vite du
     * thème est introuvable et `<x-baobab::vite>` n'émet rien — la page sort
     * sans une ligne de CSS. La publication est idempotente et se répare
     * d'elle-même (patron `PublishFontAssets`), d'où sa place ici plutôt qu'au
     * moment d'entrer en préview : un lien effacé entre-temps se rétablit à la
     * requête suivante au lieu de laisser une préview durablement nue. La
     * jonction survit à la fin de la préview, comme celle d'un thème activé
     * puis remplacé ; `UninstallModule` la retire (`UnpublishThemeAssets`).
     *
     * **Périmètre volontairement étroit** (décision du 12 août 2026) : ce que
     * le rendu exige, et rien d'autre du manifeste. Un thème en préview
     * n'écoute aucun hook, n'émet aucun webhook et n'enregistre aucun widget.
     * L'effet reste confiné à la requête de l'administrateur qui a ouvert la
     * préview, comme la session qui la porte.
     *
     * L'autoloader précède `class_exists()` pour la même raison qu'au boot :
     * le namespace d'un thème posé sous `themes/` n'est couvert par aucun
     * autoload Composer tant que le Core ne l'a pas déclaré. Le garde reste
     * nécessaire — un `module.json` peut nommer un provider que ses fichiers
     * ne définissent pas.
     *
     * `Application::register()` suffit ici, sans le `boot()` explicite que
     * `bootstrapActiveModules()` doit faire (n° 92) : à hauteur de middleware
     * l'application est entièrement démarrée, donc Laravel boote le provider
     * lui-même. L'appel est idempotent — prévisualiser le thème déjà actif ne
     * l'enregistre pas deux fois.
     */
    private function prepareInactiveTheme(Module $theme): void
    {
        app(ModuleAutoloader::class)->registerFor($theme);

        if (class_exists($theme->provider)) {
            app()->register($theme->provider);
        }

        app(PublishThemeAssets::class)($theme);
    }
}
