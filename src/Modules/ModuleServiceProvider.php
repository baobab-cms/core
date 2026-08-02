<?php

declare(strict_types=1);

namespace Baobab\Modules;

use Baobab\Access\Http\Middleware\ImpersonationGuard;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
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
     * Charge `routes/admin.php` (s'il existe) sous les mêmes middleware,
     * préfixe et nom de route que les écrans admin du Core lui-même
     * (`BaobabServiceProvider::loadAdminRoutes()`) — un module ne doit pas
     * connaître ces détails internes pour que ses routes bénéficient de la
     * même authentification/autorisation.
     */
    protected function loadModuleRoutes(): void
    {
        $adminRoutes = $this->moduleBasePath().'/routes/admin.php';

        if (is_file($adminRoutes)) {
            Route::middleware(['web', 'auth:baobab', 'verified', 'can:baobab.admin.access', ImpersonationGuard::class])
                ->prefix((string) config('baobab.admin.path', 'admin'))
                ->name('admin.')
                ->group($adminRoutes);
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
