<?php

use Baobab\Actions\Modules\InstallModule;
use Baobab\Modules\Models\Module;
use Baobab\Themes\Actions\PublishThemeAssets;
use Baobab\Themes\Actions\UnpublishThemeAssets;
use Illuminate\Support\Facades\File;

/**
 * Suivi n° 363, brique 2 — seul `UninstallCleanupTest` exerçait cette Action,
 * indirectement, par le seul chemin heureux. Ici : le no-op sur un module qui
 * n'est pas un thème, le no-op sur un lien déjà absent, l'idempotence, et la
 * garantie centrale de la classe — retirer le lien sans toucher aux vrais
 * fichiers du thème qu'il désigne.
 */
beforeEach(function () {
    config(['baobab.modules.paths' => ['local' => [fixtureModulesPath('local/*')]]]);
});

afterEach(function () {
    removeThemeLink('acme-theme');
});

it('removes the published link without touching the theme\'s real files', function () {
    $module = app(InstallModule::class)('acme/theme');
    app(PublishThemeAssets::class)($module);

    $link = public_path('themes/acme-theme');
    expect(file_exists($link))->toBeTrue();

    app(UnpublishThemeAssets::class)($module);

    expect(file_exists($link))->toBeFalse()
        ->and(File::isDirectory(fixtureModulesPath('local/acme-theme/public')))->toBeTrue()
        ->and(File::get(fixtureModulesPath('local/acme-theme/public/build/.vite/manifest.json')))
        ->toContain('resources/assets/css/app.css');
});

it('is a no-op on a module that is not a theme', function () {
    $module = Module::create([
        'name' => 'acme/newsletter',
        'title' => 'Newsletter',
        'type' => 'module',
        'version' => '1.0.0',
        'provider' => 'Acme\\Newsletter\\Providers\\NewsletterServiceProvider',
        'source' => 'local',
        'path' => fixtureModulesPath('local/acme-theme'),
        'status' => 'active',
        'manifest' => [],
    ]);

    File::ensureDirectoryExists(public_path('themes'));
    File::put(public_path('themes/untouched.txt'), 'still here');

    app(UnpublishThemeAssets::class)($module);

    expect(File::exists(public_path('themes/untouched.txt')))->toBeTrue();

    File::delete(public_path('themes/untouched.txt'));
});

it('is a no-op when the link was never published', function () {
    $module = app(InstallModule::class)('acme/theme');

    expect(file_exists(public_path('themes/acme-theme')))->toBeFalse();

    app(UnpublishThemeAssets::class)($module);

    expect(file_exists(public_path('themes/acme-theme')))->toBeFalse();
});

it('is idempotent — a second call is a no-op', function () {
    $module = app(InstallModule::class)('acme/theme');
    app(PublishThemeAssets::class)($module);

    app(UnpublishThemeAssets::class)($module);
    app(UnpublishThemeAssets::class)($module);

    expect(file_exists(public_path('themes/acme-theme')))->toBeFalse();
});
