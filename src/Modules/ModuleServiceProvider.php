<?php

declare(strict_types=1);

namespace Baobab\Modules;

use Baobab\Access\Http\Middleware\ImpersonationGuard;
use Baobab\Api\Http\Middleware\EnsureApiEnabled;
use Baobab\Api\Http\Middleware\HandleApiCors;
use Baobab\Themes\Http\Middleware\ResolveActiveTheme;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use ReflectionClass;
use RuntimeException;

/**
 * Classe de base optionnelle pour le Service Provider d'un module qui a
 * besoin de charger ses propres routes/vues (spec-modules §2.3) — jamais
 * nécessaire pour un Content Type (le CRUD admin/API/front passe par le
 * contrôleur générique du Core, cf. `ContentTypeModuleGenerator`), mais
 * indispensable pour un module Wizard Studio (spec-modules §5.2 étape 4,
 * M8 point 1 Pass A2) dont chaque entité a son propre contrôleur.
 *
 * Documentée mais jamais construite avant ce point (grep vide sur tout le
 * Core) : aucun module, généré ou écrit à la main, n'avait eu besoin de
 * routes propres jusqu'ici. Volontairement partielle par rapport à
 * l'exemple complet de la spec (`loadModuleMigrations()`/
 * `loadModuleTranslations()`/`registerModuleConfig()` n'existent pas ici) —
 * seuls les deux helpers réellement consommés par le générateur A2 sont
 * construits ; les migrations passent déjà par le mécanisme impératif
 * `InstallModule::runMigrations()` (suivi n° 82), pas par
 * `loadMigrationsFrom()`.
 */
abstract class ModuleServiceProvider extends ServiceProvider
{
    /**
     * Charge `routes/admin.php`/`web.php`/`api.php` (s'ils existent) sous
     * les mêmes middleware/préfixe/nom que les groupes équivalents du Core
     * lui-même (`BaobabServiceProvider::loadAdminRoutes()`/
     * `registerPublicRoutes()`/`loadApiRoutes()`) — un module ne doit pas
     * connaître ces détails internes pour que ses routes bénéficient de la
     * même authentification/autorisation/CORS.
     *
     * Appelée par `bootstrapActiveModules()`, désormais délibérément
     * enregistrée **avant** la route publique générique du Core
     * (`{prefix}/{slug?}`) — sinon celle-ci intercepterait toute route
     * `routes/web.php` d'un module Studio avant qu'elle ne soit jamais
     * essayée, Laravel matchant les routes dans l'ordre d'enregistrement
     * (cf. commentaire dans `BaobabServiceProvider::boot()`).
     */
    protected function loadModuleRoutes(): void
    {
        $base = $this->moduleBasePath();

        $adminRoutes = $base.'/routes/admin.php';

        if (is_file($adminRoutes)) {
            Route::middleware(['web', 'auth:baobab', 'verified', 'can:baobab.admin.access', ImpersonationGuard::class])
                ->prefix((string) config('baobab.admin.path', 'admin'))
                ->name('admin.')
                ->group($adminRoutes);
        }

        $webRoutes = $base.'/routes/web.php';

        if (is_file($webRoutes)) {
            Route::middleware(['web', ResolveActiveTheme::class])->group($webRoutes);
        }

        $apiRoutes = $base.'/routes/api.php';

        if (is_file($apiRoutes)) {
            // SubstituteBindings : absent du groupe équivalent du Core
            // (`BaobabServiceProvider::loadApiRoutes()`) — sans conséquence
            // là-bas, `Api\Http\Controllers\ContentController` résolvant ses
            // entités "à la main" (slug + id, jamais un paramètre de route
            // Eloquent typé). Un contrôleur Studio généré, lui, type-hint
            // directement le modèle (`show(Car $car)`) : sans ce middleware,
            // le conteneur construit un `new Car` vide au lieu de résoudre
            // le binding de route — bug réel découvert en testant cette
            // passe (`{"data":{"id":null,...}}` au lieu de la ligne réelle).
            Route::middleware([HandleApiCors::class, EnsureApiEnabled::class, EnsureFrontendRequestsAreStateful::class, 'throttle:baobab-api', SubstituteBindings::class])
                ->prefix('api/v1')
                ->name('api.v1.')
                ->group($apiRoutes);
        }
    }

    /**
     * Charge `resources/views` sous le namespace donné (patron
     * `loadViewsFrom()` standard Laravel) — recommandé : le slug du module,
     * pour que les vues générées soient référencées `{slug}::...`.
     */
    protected function loadModuleViews(string $namespace): void
    {
        $path = $this->moduleBasePath().'/resources/views';

        if (is_dir($path)) {
            $this->loadViewsFrom($path, $namespace);
        }
    }

    /**
     * Racine du module déduite de l'emplacement du fichier de la classe
     * concrète (`{moduleDir}/src/Providers/{X}ServiceProvider.php`) — évite
     * d'avoir à faire porter le chemin en dur par chaque provider généré.
     */
    private function moduleBasePath(): string
    {
        $file = (new ReflectionClass($this))->getFileName();

        if ($file === false) {
            throw new RuntimeException(static::class.' has no source file to derive its module path from.');
        }

        return dirname($file, 3);
    }
}
