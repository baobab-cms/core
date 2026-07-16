<?php

use Baobab\Actions\Modules\InstallModule;
use Baobab\Modules\Models\Module;
use Baobab\Notify\Exceptions\InvalidNotificationException;

it('installs a module whose mail_template resolves within the same manifest', function () {
    config(['baobab.modules.paths' => ['local' => [fixtureModulesPath('local/*')]]]);

    $module = app(InstallModule::class)('acme/notifier');

    expect($module->status)->toBe('installed')
        ->and($module->manifest['notifications'][0]['key'])->toBe('acme.notifier.pending');
});

it('refuses to install a module whose notification references an unresolvable mail_template', function () {
    config(['baobab.modules.paths' => ['local' => [fixtureModulesPath('local/*')]]]);

    app(InstallModule::class)('acme/notifier-broken');
})->throws(InvalidNotificationException::class);

it('does not persist the module row when notification validation fails', function () {
    config(['baobab.modules.paths' => ['local' => [fixtureModulesPath('local/*')]]]);

    try {
        app(InstallModule::class)('acme/notifier-broken');
    } catch (InvalidNotificationException) {
        // attendu
    }

    expect(Module::where('name', 'acme/notifier-broken')->exists())->toBeFalse();
});

it('accepts a notification whose mail_template resolves against a Core template', function () {
    config(['baobab.modules.paths' => ['local' => [fixtureModulesPath('local/*')]]]);

    $module = app(InstallModule::class)('acme/notifier-core-template');

    expect($module->status)->toBe('installed');
});
