<?php

declare(strict_types=1);

namespace Baobab;

use Baobab\Console\Commands\HookListCommand;
use Baobab\Console\Commands\ModuleActivateCommand;
use Baobab\Console\Commands\ModuleDeactivateCommand;
use Baobab\Console\Commands\ModuleInstallCommand;
use Baobab\Console\Commands\ModuleListCommand;
use Baobab\Console\Commands\ModuleUninstallCommand;
use Baobab\Facades\Hook;
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

        // Load active module providers, wire declarative hooks, then signal the
        // kernel is ready. Order matters: providers first (they may register
        // bindings needed by listeners), then hook wiring, then the boot event.
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
            ]);
        }
    }

    /**
     * For every active module (single DB query):
     *   1. Register its ServiceProvider if the class is autoloadable.
     *   2. Wire its manifest hooks.listens into the HookRegistry.
     *
     * Skipped silently when the DB is unavailable or not yet migrated.
     */
    private function bootstrapActiveModules(): void
    {
        try {
            if (! Schema::hasTable('modules')) {
                return;
            }

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
        } catch (Throwable) {
            // DB unavailable or not yet migrated — skip silently.
        }
    }
}
