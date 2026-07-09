<?php

declare(strict_types=1);

namespace Baobab\Tests;

use Baobab\BaobabServiceProvider;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [BaobabServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        /** @var Application $app */
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
    }
}
