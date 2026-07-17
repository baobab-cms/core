<?php

use Baobab\Actions\Modules\InstallModule;
use Baobab\Themes\Actions\ActivateTheme;
use Illuminate\Support\Facades\Blade;

beforeEach(function () {
    config(['baobab.modules.paths' => ['local' => [fixtureModulesPath('local/*')]]]);
});

afterEach(function () {
    removeThemeLink('acme-theme');
});

it('emits the stylesheet link resolved from the active theme manifest', function () {
    app(InstallModule::class)('acme/theme');
    app(ActivateTheme::class)('acme/theme');

    $html = Blade::render('<x-baobab::vite entry="app" />');

    expect($html)->toContain('<link rel="stylesheet" href="/themes/acme-theme/build/assets/app-test.css">');
});

it('renders nothing when no theme is active', function () {
    $html = Blade::render('<x-baobab::vite entry="app" />');

    expect(trim($html))->toBe('');
});

it('renders nothing when the requested entry is not in the manifest', function () {
    app(InstallModule::class)('acme/theme');
    app(ActivateTheme::class)('acme/theme');

    $html = Blade::render('<x-baobab::vite entry="unknown" />');

    expect(trim($html))->toBe('');
});
