<?php

use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    config(['baobab.modules.paths' => ['local' => [fixtureModulesPath('local/*')]]]);
});

it('delegates to baobab:theme:validate and succeeds for a valid theme', function () {
    $exitCode = Artisan::call('baobab:theme:lint', ['name' => 'acme/theme']);

    expect($exitCode)->toBe(0)
        ->and(Artisan::output())->toContain('is valid');
});

it('delegates to baobab:theme:validate and fails for an invalid theme', function () {
    $exitCode = Artisan::call('baobab:theme:lint', ['name' => 'acme/theme-invalid']);

    expect($exitCode)->toBe(1)
        ->and(Artisan::output())->toContain('blocking violation');
});
