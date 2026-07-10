<?php

use Baobab\Modules\Models\Module;
use Baobab\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

pest()->extend(TestCase::class)->in('Unit');
pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');

function fixtureModulesPath(string $path = ''): string
{
    return __DIR__.'/Fixtures/modules'.($path !== '' ? '/'.$path : '');
}

function makeActiveModule(string $name = 'acme/manual'): Module
{
    return Module::create([
        'name' => $name,
        'title' => $name,
        'type' => 'module',
        'version' => '1.0.0',
        'provider' => 'Acme\\Manual\\Providers\\ManualServiceProvider',
        'source' => 'local',
        'path' => '/tmp/'.$name,
        'manifest' => [],
        'status' => 'active',
    ]);
}
