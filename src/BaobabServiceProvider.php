<?php

declare(strict_types=1);

namespace Baobab;

use Baobab\Access\AccessManager;
use Baobab\Access\Facades\Access;
use Baobab\Auth\TwoFactorManager;
use Baobab\Console\Commands\HookListCommand;
use Baobab\Console\Commands\ModuleActivateCommand;
use Baobab\Console\Commands\ModuleDeactivateCommand;
use Baobab\Console\Commands\ModuleInstallCommand;
use Baobab\Console\Commands\ModuleListCommand;
use Baobab\Console\Commands\ModuleUninstallCommand;
use Baobab\Console\Commands\SuperAdminCommand;
use Baobab\Facades\Hook;
use Baobab\Hooks\HookRegistry;
use Baobab\Modules\Models\Module;
use Baobab\Modules\ModuleDiscovery;
use Baobab\Users\Models\User;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use PragmaRX\Google2FA\Google2FA;
use Spatie\Permission\PermissionServiceProvider;
use Throwable;

class BaobabServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/baobab.php', 'baobab');

        $this->registerSpatieConfig();

        $this->app->register(PermissionServiceProvider::class);

        $this->app->singleton(HookRegistry::class);

        $this->app->singleton(AccessManager::class);

        $this->app->singleton(TwoFactorManager::class, fn () => new TwoFactorManager(new Google2FA));

        $this->app->bind(ModuleDiscovery::class, function (Application $app) {
            /** @var array<string, list<string>> $paths */
            $paths = $app->make('config')->get('baobab.modules.paths', []);

            return new ModuleDiscovery($paths);
        });

        $this->registerGuard();
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'baobab');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->publishes([
            __DIR__.'/../config/baobab.php' => config_path('baobab.php'),
        ], 'baobab-config');

        // Super Admin bypasses all Gate checks.
        Gate::before(function (User $user, string $ability): ?bool {
            return $user->hasRole('super-admin', 'baobab') ? true : null;
        });

        // Alias facade.
        $this->app->alias(AccessManager::class, Access::class);

        $this->bootstrapActiveModules();

        Hook::action('baobab.booted');

        if ($this->app->runningInConsole()) {
            $this->commands([
                ModuleListCommand::class,
                ModuleInstallCommand::class,
                ModuleActivateCommand::class,
                ModuleDeactivateCommand::class,
                ModuleUninstallCommand::class,
                HookListCommand::class,
                SuperAdminCommand::class,
            ]);
        }
    }

    private function registerGuard(): void
    {
        /** @var string $userModel */
        $userModel = $this->app->make('config')->get(
            'baobab.auth.user_model',
            User::class,
        );

        $this->app->make('config')->set('auth.guards.baobab', [
            'driver' => 'session',
            'provider' => 'baobab_users',
        ]);

        $this->app->make('config')->set('auth.providers.baobab_users', [
            'driver' => 'eloquent',
            'model' => $userModel,
        ]);
    }

    private function registerSpatieConfig(): void
    {
        $this->app->make('config')->set('permission.table_names', [
            'roles' => 'baobab_roles',
            'permissions' => 'baobab_permissions',
            'model_has_permissions' => 'baobab_model_has_permissions',
            'model_has_roles' => 'baobab_model_has_roles',
            'role_has_permissions' => 'baobab_role_has_permissions',
        ]);

        $this->app->make('config')->set('permission.column_names', [
            'role_pivot_key' => null,
            'permission_pivot_key' => null,
            'model_morph_key' => 'model_id',
            'team_foreign_key' => 'team_id',
        ]);

        $this->app->make('config')->set('permission.teams', false);
        $this->app->make('config')->set('permission.use_passport_client_credentials', false);
        $this->app->make('config')->set('permission.display_permission_in_exception', false);
        $this->app->make('config')->set('permission.display_role_in_exception', false);
        $this->app->make('config')->set('permission.enable_wildcard_permission', false);
        $this->app->make('config')->set('permission.cache.expiration_time', \DateInterval::createFromDateString('24 hours'));
        $this->app->make('config')->set('permission.cache.key', 'spatie.permission.cache');
        $this->app->make('config')->set('permission.cache.store', 'default');
    }

    /**
     * For every active module (single DB query):
     *   1. Register its ServiceProvider if the class is autoloadable.
     *   2. Wire its manifest hooks.listens into the HookRegistry.
     *
     * Skipped silently when the DB is unavailable or not yet migrated.
     * Any unexpected error is logged as a warning so it stays visible.
     */
    private function bootstrapActiveModules(): void
    {
        try {
            if (! Schema::hasTable('modules')) {
                return;
            }
        } catch (Throwable) {
            // DB not reachable yet (fresh install, offline test env, etc.)
            return;
        }

        try {
            /** @var HookRegistry $registry */
            $registry = $this->app->make(HookRegistry::class);

            Module::where('status', 'active')->each(function (Module $module) use ($registry): void {
                if (class_exists($module->provider)) {
                    $this->app->register($module->provider);
                }

                foreach ($module->manifest['hooks']['listens'] ?? [] as $hook => $listener) {
                    $registry->listen($hook, $listener);
                }
            });
        } catch (Throwable $e) {
            Log::warning('[Baobab] Could not bootstrap active modules: '.$e->getMessage());
        }
    }
}
