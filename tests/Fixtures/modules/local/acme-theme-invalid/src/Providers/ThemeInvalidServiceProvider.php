<?php

declare(strict_types=1);

namespace Acme\ThemeInvalid\Providers;

use Illuminate\Support\ServiceProvider;

final class ThemeInvalidServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        eval('$x = 1;');
    }
}
