<?php

use Baobab\Actions\Modules\InstallModule;
use Baobab\Mail\Exceptions\InvalidMailTemplateException;
use Baobab\Modules\Models\Module;

it('installs a module whose required mail variable appears in the default template', function () {
    config(['baobab.modules.paths' => ['local' => [fixtureModulesPath('local/*')]]]);

    $module = app(InstallModule::class)('acme/mailer');

    expect($module->status)->toBe('installed')
        ->and($module->manifest['mails'][0]['key'])->toBe('acme.mailer.welcome');
});

it('refuses to install a module whose required mail variable is missing from the default template', function () {
    config(['baobab.modules.paths' => ['local' => [fixtureModulesPath('local/*')]]]);

    app(InstallModule::class)('acme/mailer-broken');
})->throws(InvalidMailTemplateException::class);

it('does not persist the module row when mail template validation fails', function () {
    config(['baobab.modules.paths' => ['local' => [fixtureModulesPath('local/*')]]]);

    try {
        app(InstallModule::class)('acme/mailer-broken');
    } catch (InvalidMailTemplateException) {
        // attendu
    }

    expect(Module::where('name', 'acme/mailer-broken')->exists())->toBeFalse();
});
