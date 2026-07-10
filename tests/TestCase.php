<?php

declare(strict_types=1);

namespace Baobab\Tests;

use Baobab\BaobabServiceProvider;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        // No Vite build exists in the Testbench skeleton — views using @vite
        // (the admin layout) would otherwise throw a manifest-not-found error.
        $this->withoutVite();
    }

    /**
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [BaobabServiceProvider::class];
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Fixtures/migrations');
    }

    protected function getEnvironmentSetUp($app): void
    {
        /** @var Application $app */
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('app.locale', 'fr');
        $app['config']->set('app.fallback_locale', 'fr');
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
    }
}
