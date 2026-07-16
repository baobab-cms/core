<?php

use Baobab\Actions\Modules\ActivateModule;
use Baobab\Actions\Modules\InstallModule;
use Baobab\Notify\Exceptions\NotificationNotFoundException;
use Baobab\Notify\NotificationRegistry;

it('resolves a Core-declared notification from config', function () {
    config(['baobab.notifications.declarations' => [
        ['key' => 'core.test', 'channels' => ['database'], 'configurable' => true],
    ]]);

    $declaration = app(NotificationRegistry::class)->find('core.test');

    expect($declaration->key)->toBe('core.test')
        ->and($declaration->channels)->toBe(['database']);
});

it('resolves a notification declared by an active module', function () {
    config(['baobab.modules.paths' => ['local' => [fixtureModulesPath('local/*')]]]);
    app(InstallModule::class)('acme/notifier');
    app(ActivateModule::class)('acme/notifier');

    $declaration = app(NotificationRegistry::class)->find('acme.notifier.pending');

    expect($declaration->key)->toBe('acme.notifier.pending')
        ->and($declaration->channels)->toBe(['database', 'mail'])
        ->and($declaration->mailTemplate)->toBe('acme.notifier.pending')
        ->and($declaration->configurable)->toBeTrue();
});

it('does not resolve a notification declared by an inactive module', function () {
    config(['baobab.modules.paths' => ['local' => [fixtureModulesPath('local/*')]]]);
    app(InstallModule::class)('acme/notifier'); // installé, pas activé

    app(NotificationRegistry::class)->find('acme.notifier.pending');
})->throws(NotificationNotFoundException::class);

it('throws for an unknown notification key', function () {
    app(NotificationRegistry::class)->find('core.does_not_exist');
})->throws(NotificationNotFoundException::class);

it('lists only configurable declarations across Core and active modules', function () {
    config(['baobab.notifications.declarations' => [
        ['key' => 'core.configurable', 'channels' => ['database'], 'configurable' => true],
        ['key' => 'core.locked', 'channels' => ['database'], 'configurable' => false],
    ]]);

    $keys = collect(app(NotificationRegistry::class)->configurable())->pluck('key')->all();

    expect($keys)->toContain('core.configurable')
        ->not->toContain('core.locked');
});
