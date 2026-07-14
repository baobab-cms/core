<?php

use Baobab\Actions\Modules\ActivateModule;
use Baobab\Actions\Modules\InstallModule;
use Illuminate\Support\Facades\Artisan;

it('lists the core.test template', function () {
    $exitCode = Artisan::call('baobab:mail:templates');
    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('core.test')
        ->and($output)->toContain('core');
});

it('lists a template declared by an active module', function () {
    config(['baobab.modules.paths' => ['local' => [fixtureModulesPath('local/*')]]]);
    app(InstallModule::class)('acme/mailer');
    app(ActivateModule::class)('acme/mailer');

    $exitCode = Artisan::call('baobab:mail:templates');
    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('acme.mailer.welcome')
        ->and($output)->toContain('acme/mailer');
});
