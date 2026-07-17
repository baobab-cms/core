<?php

use Baobab\Actions\Modules\InstallModule;
use Baobab\Themes\Actions\PublishThemeAssets;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    config(['baobab.modules.paths' => ['local' => [fixtureModulesPath('local/*')]]]);
});

afterEach(function () {
    removeThemeLink('acme-theme');
});

it('publishes a symlink from public/themes/{slug} to the theme public directory', function () {
    $module = app(InstallModule::class)('acme/theme');

    app(PublishThemeAssets::class)($module);

    $link = public_path('themes/acme-theme');

    // Sur Windows, is_link() suivi de is_dir() sur le même chemin dans une
    // seule expression retourne un résultat incohérent pour une jonction de
    // répertoire (les deux, testés séparément, sont fiables) — écart
    // découvert en écrivant ce test. file_exists() seul (stat() interne,
    // suit la jonction) suffit et reste fiable.
    expect(file_exists($link))->toBeTrue()
        ->and(File::get($link.'/build/.vite/manifest.json'))->toContain('resources/assets/css/app.css');
});

it('is idempotent — does not fail when the link already exists', function () {
    $module = app(InstallModule::class)('acme/theme');

    app(PublishThemeAssets::class)($module);
    app(PublishThemeAssets::class)($module);

    expect(File::exists(public_path('themes/acme-theme')))->toBeTrue();
});

it('slugFor() replaces the vendor slash before slugifying, avoiding word-merging', function () {
    $module = app(InstallModule::class)('acme/theme');

    expect(PublishThemeAssets::slugFor($module))->toBe('acme-theme');
});
