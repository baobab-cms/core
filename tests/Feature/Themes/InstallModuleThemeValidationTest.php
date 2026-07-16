<?php

use Baobab\Actions\Modules\InstallModule;
use Baobab\Modules\Models\Module;
use Baobab\Themes\Validation\Exceptions\ThemeValidationFailedException;

beforeEach(function () {
    config(['baobab.modules.paths' => ['local' => [fixtureModulesPath('local/*')]]]);
});

it('refuses to install a theme that fails validation, and persists nothing', function () {
    expect(fn () => app(InstallModule::class)('acme/theme-invalid'))
        ->toThrow(ThemeValidationFailedException::class);

    expect(Module::where('name', 'acme/theme-invalid')->exists())->toBeFalse();
});

it('reports every blocking violation the invalid fixture cumulates', function () {
    try {
        app(InstallModule::class)('acme/theme-invalid');
        $this->fail('Expected ThemeValidationFailedException.');
    } catch (ThemeValidationFailedException $e) {
        $messages = collect($e->violations())->map(fn ($v) => $v->message)->implode(' | ');

        expect($messages)->toContain('@php')
            ->and($messages)->toContain('eval()')
            ->and($messages)->toContain('Fichier PHP interdit');
    }
});

it('installs a valid theme normally', function () {
    $module = app(InstallModule::class)('acme/theme');

    expect($module->status)->toBe('installed');
});
