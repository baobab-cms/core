<?php

declare(strict_types=1);

namespace Baobab;

use Baobab\Console\Commands\HookListCommand;
use Baobab\Console\Commands\ModuleActivateCommand;
use Baobab\Console\Commands\ModuleDeactivateCommand;
use Baobab\Console\Commands\ModuleInstallCommand;
use Baobab\Console\Commands\ModuleListCommand;
use Baobab\Console\Commands\ModuleUninstallCommand;
use Baobab\Hooks\HookRegistry;
use Baobab\Modules\Models\Module;
use Baobab\Modules\ModuleDiscovery;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Throwable;

class BaobabServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/baobab.php', 'baobab');

        $this->app->singleton(HookRegistry::class);

        $this->app->bind(ModuleDiscovery::class, function (Application $app) {
            /** @var array<string, list<string>> $paths */
            $paths = $app->make('config')->get('baobab.modules.paths', []);

            return new ModuleDiscovery($paths);
        });
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'baobab');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->publishes([
            __DIR__.'/../config/baobab.php' => config_path('baobab.php'),
        ], 'baobab-config');

        $this->wireDeclarativeHooks();

        if ($this->app->runningInConsole()) {
            $this->commands([
                ModuleListCommand::class,
                ModuleInstallCommand::class,
                ModuleActivateCommand::class,
                ModuleDeactivateCommand::class,
                ModuleUninstallCommand::class,
                HookListCommand::class,
            ]);
        }
    }

    /**
     * Wire declarative hook listeners for all active modules on every boot
     * (handles server restarts with modules already active in the database).
     * Runtime activation is handled separately by ActivateModule action.
     */
    private function wireDeclarativeHooks(): void
    {
        try {
            if (! Schema::hasTable('modules')) {
                return;
            }

            /** @var HookRegistry $registry */
            $registry = $this->app->make(HookRegistry::class);

            Module::where('status', 'active')->each(function (Module $module) use ($registry): void {
                foreach ($module->manifest['hooks']['listens'] ?? [] as $hook => $listener) {
                    $registry->listen($hook, $listener);
                }
            });
        } catch (Throwable) {
            // DB unavailable or not yet migrated — skip silently.
        }
    }
}
