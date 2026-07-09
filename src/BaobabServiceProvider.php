<?php

namespace Baobab;

use Illuminate\Support\ServiceProvider;

class BaobabServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'baobab');
    }
}
