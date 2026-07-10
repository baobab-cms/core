<?php

declare(strict_types=1);

namespace Baobab;

use Baobab\Console\Commands\ModuleActivateCommand;
use Baobab\Console\Commands\ModuleDeactivateCommand;
use Baobab\Console\Commands\ModuleInstallCommand;
use Baobab\Console\Commands\ModuleListCommand;
use Baobab\Console\Commands\ModuleUninstallCommand;
use Baobab\Hooks\HookRegistry;
use Baobab\Modules\ModuleDiscovery;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

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

        if ($this->app->runningInConsole()) {
            $this->commands([
                ModuleListCommand::class,
                ModuleInstallCommand::class,
                ModuleActivateCommand::class,
                ModuleDeactivateCommand::class,
                ModuleUninstallCommand::class,
            ]);
        }
    }
}
