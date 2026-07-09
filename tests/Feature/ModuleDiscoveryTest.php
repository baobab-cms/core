<?php

use Baobab\Modules\DiscoveredModule;
use Baobab\Modules\ModuleDiscovery;

it('discovers modules across configured locations', function () {
    $discovery = new ModuleDiscovery([
        'local' => [fixtureModulesPath('local/*')],
        'composer' => [fixtureModulesPath('composer/*')],
    ]);

    $discovered = $discovery->scan();

    expect($discovered->has('acme/widgets'))->toBeTrue()
        ->and($discovered->has('acme/other'))->toBeTrue();
});

it('gives priority to local modules over composer modules with the same name', function () {
    $discovery = new ModuleDiscovery([
        'local' => [fixtureModulesPath('local/*')],
        'composer' => [fixtureModulesPath('composer/*')],
    ]);

    $widgets = $discovery->scan()->get('acme/widgets');
    assert($widgets instanceof DiscoveredModule);

    expect($widgets->source)->toBe('local')
        ->and($widgets->manifest->version())->toBe('2.0.0');
});

it('skips directories with an invalid manifest instead of failing the whole scan', function () {
    $discovery = new ModuleDiscovery([
        'local' => [fixtureModulesPath('local/*')],
        'invalid' => [fixtureModulesPath('invalid/*')],
    ]);

    $discovered = $discovery->scan();

    expect($discovered->has('acme/broken'))->toBeFalse()
        ->and($discovered->has('acme/widgets'))->toBeTrue();
});

it('exposes requires and hooks from a discovered manifest', function () {
    $discovery = new ModuleDiscovery([
        'composer' => [fixtureModulesPath('composer/*')],
    ]);

    $other = $discovery->scan()->get('acme/other');
    assert($other instanceof DiscoveredModule);

    expect($other->manifest->requiresModules())->toBe(['acme/widgets' => '^2.0'])
        ->and($other->manifest->hooksListened())->toBe(['baobab.booted' => 'Acme\\Other\\Hooks\\OnBooted']);
});

it('returns an empty collection when no module is found', function () {
    $discovery = new ModuleDiscovery([
        'local' => [fixtureModulesPath('does-not-exist/*')],
    ]);

    expect($discovery->scan())->toBeEmpty();
});
