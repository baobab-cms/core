<?php

use Baobab\Actions\Modules\ActivateModule;
use Baobab\Actions\Modules\InstallModule;
use Baobab\Facades\Hook;
use Illuminate\Support\Facades\Artisan;

// ── Câblage déclaratif à l'activation ─────────────────────────────────────────

it('activating a module registers its declared hook listeners', function () {
    config(['baobab.modules.paths' => [
        'local' => [fixtureModulesPath('local/*')],
        'composer' => [fixtureModulesPath('composer/*')],
    ]]);

    // acme/other requires acme/widgets and declares hooks.listens
    app(InstallModule::class)('acme/widgets');
    app(InstallModule::class)('acme/other');
    app(ActivateModule::class)('acme/widgets');
    app(ActivateModule::class)('acme/other');

    $actions = Hook::actions();

    expect($actions)->toHaveKey('baobab.booted')
        ->and($actions['baobab.booted'][0]['listener'])->toBe('Acme\\Other\\Hooks\\OnBooted');
});

it('a module with no hooks.listens does not register any listener on activation', function () {
    config(['baobab.modules.paths' => ['local' => [fixtureModulesPath('local/*')]]]);

    app(InstallModule::class)('acme/blog');
    app(ActivateModule::class)('acme/blog');

    // acme/blog has no hooks.listens — only the module.activated hook from the action itself
    $registeredForBooted = Hook::actions()['baobab.booted'] ?? [];

    expect($registeredForBooted)->toBeEmpty();
});

// ── hook:list ─────────────────────────────────────────────────────────────────

it('hook:list outputs "no hooks registered" when registry is empty', function () {
    $exitCode = Artisan::call('hook:list');
    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('No hooks registered.');
});

it('hook:list shows registered actions and filters in a table', function () {
    Hook::listen('demo.action', fn () => null, priority: 5);
    Hook::modify('demo.filter', fn (mixed $v) => $v, priority: 10);

    $exitCode = Artisan::call('hook:list');
    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('demo.action')
        ->and($output)->toContain('action')
        ->and($output)->toContain('demo.filter')
        ->and($output)->toContain('filter');
});
