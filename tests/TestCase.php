<?php

namespace Baobab\Tests;

use Baobab\BaobabServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [BaobabServiceProvider::class];
    }
}
