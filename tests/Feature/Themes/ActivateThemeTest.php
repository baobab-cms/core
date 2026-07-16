<?php

use Baobab\Actions\Modules\InstallModule;
use Baobab\Facades\Hook;
use Baobab\Modules\Models\Module;
use Baobab\Themes\Actions\ActivateTheme;
use Baobab\Themes\Exceptions\NotAThemeException;
use Baobab\Themes\Models\ThemeMenuLocation;
use Baobab\Themes\Models\ThemeWidgetZone;

beforeEach(function () {
    config(['baobab.modules.paths' => ['local' => [fixtureModulesPath('local/*')]]]);
});

it('activates a theme and reserves its declared menu locations and widget zones', function () {
    app(InstallModule::class)('acme/theme');

    $activated = app(ActivateTheme::class)('acme/theme');

    expect($activated->status)->toBe('active')
        ->and(ThemeMenuLocation::where('key', 'primary')->value('is_active'))->toBeTrue()
        ->and(ThemeMenuLocation::where('key', 'footer')->value('is_active'))->toBeTrue()
        ->and(ThemeWidgetZone::where('key', 'sidebar')->value('is_active'))->toBeTrue();
});

it('atomically deactivates the previously active theme when activating another', function () {
    app(InstallModule::class)('acme/theme');
    app(InstallModule::class)('acme/theme-child');
    app(ActivateTheme::class)('acme/theme');

    app(ActivateTheme::class)('acme/theme-child');

    expect(Module::where('name', 'acme/theme')->value('status'))->toBe('inactive')
        ->and(Module::where('name', 'acme/theme-child')->value('status'))->toBe('active')
        ->and(Module::where('type', 'theme')->where('status', 'active')->count())->toBe(1);
});

it('orphans a menu location no longer declared by the newly activated theme, without deleting it', function () {
    app(InstallModule::class)('acme/theme');
    app(InstallModule::class)('acme/theme-child');
    app(ActivateTheme::class)('acme/theme');

    app(ActivateTheme::class)('acme/theme-child');

    // acme/theme-child ne déclare que "primary" (menus) et aucune widget_zone.
    expect(ThemeMenuLocation::where('key', 'primary')->value('is_active'))->toBeTrue()
        ->and(ThemeMenuLocation::where('key', 'footer')->value('is_active'))->toBeFalse()
        ->and(ThemeMenuLocation::where('key', 'footer')->exists())->toBeTrue()
        ->and(ThemeWidgetZone::where('key', 'sidebar')->value('is_active'))->toBeFalse();
});

it('reactivating a theme restores its previously orphaned locations without duplicating rows', function () {
    app(InstallModule::class)('acme/theme');
    app(InstallModule::class)('acme/theme-child');
    app(ActivateTheme::class)('acme/theme');
    app(ActivateTheme::class)('acme/theme-child');

    app(ActivateTheme::class)('acme/theme');

    expect(ThemeMenuLocation::where('key', 'footer')->value('is_active'))->toBeTrue()
        ->and(ThemeMenuLocation::where('key', 'footer')->count())->toBe(1);
});

it('fires baobab.theme.activated with the previous and newly activated theme', function () {
    app(InstallModule::class)('acme/theme');
    app(InstallModule::class)('acme/theme-child');
    app(ActivateTheme::class)('acme/theme');

    $captured = [];
    Hook::listen('baobab.theme.activated', function ($previous, $activated) use (&$captured) {
        $captured = [$previous, $activated];
    });

    app(ActivateTheme::class)('acme/theme-child');

    expect($captured[0]->name)->toBe('acme/theme')
        ->and($captured[1]->name)->toBe('acme/theme-child');
});

it('refuses to activate a non-theme module', function () {
    app(InstallModule::class)('acme/blog');

    app(ActivateTheme::class)('acme/blog');
})->throws(NotAThemeException::class);
