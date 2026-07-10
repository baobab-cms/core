<?php

declare(strict_types=1);

namespace Baobab\Tests\Fixtures;

use Illuminate\Support\ServiceProvider;

final class TestModuleServiceProvider extends ServiceProvider
{
    public static bool $booted = false;

    public function boot(): void
    {
        self::$booted = true;
    }
}
