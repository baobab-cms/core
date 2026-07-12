<?php

use Baobab\Media\Conversions\PresetRegistry;

it('registers the three Core presets at boot', function () {
    $registry = app(PresetRegistry::class);

    expect($registry->has('thumb'))->toBeTrue()
        ->and($registry->has('medium'))->toBeTrue()
        ->and($registry->has('large'))->toBeTrue();

    expect($registry->get('thumb'))->toBe(['width' => 300, 'fit' => 'contain'])
        ->and($registry->get('medium'))->toBe(['width' => 768, 'fit' => 'contain'])
        ->and($registry->get('large'))->toBe(['width' => 1600, 'fit' => 'contain']);
});

it('defaults fit to contain when a preset omits it', function () {
    $registry = new PresetRegistry;
    $registry->register('custom', ['width' => 100]);

    expect($registry->get('custom'))->toBe(['width' => 100, 'fit' => 'contain']);
});

it('reports unknown presets as absent', function () {
    $registry = new PresetRegistry;

    expect($registry->has('does-not-exist'))->toBeFalse();
});
