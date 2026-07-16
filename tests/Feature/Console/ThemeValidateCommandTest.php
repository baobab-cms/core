<?php

use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    config(['baobab.modules.paths' => ['local' => [fixtureModulesPath('local/*')]]]);
});

it('succeeds and reports no violations for a valid theme, without installing it', function () {
    $exitCode = Artisan::call('baobab:theme:validate', ['name' => 'acme/theme']);

    expect($exitCode)->toBe(0)
        ->and(Artisan::output())->toContain('is valid');
});

it('fails and lists blocking violations for an invalid theme', function () {
    $exitCode = Artisan::call('baobab:theme:validate', ['name' => 'acme/theme-invalid']);
    $output = Artisan::output();

    expect($exitCode)->toBe(1)
        ->and($output)->toContain('blocking violation')
        ->and($output)->toContain('eval()');
});

it('fails clearly when the theme cannot be discovered', function () {
    $exitCode = Artisan::call('baobab:theme:validate', ['name' => 'acme/does-not-exist']);

    expect($exitCode)->toBe(1)
        ->and(Artisan::output())->toContain('not found');
});
