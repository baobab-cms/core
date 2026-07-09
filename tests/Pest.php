<?php

use Baobab\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

pest()->extend(TestCase::class)->in('Unit');
pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');

function fixtureModulesPath(string $path = ''): string
{
    return __DIR__.'/Fixtures/modules'.($path !== '' ? '/'.$path : '');
}
