<?php

use Baobab\Actions\Modules\InstallModule;
use Baobab\Modules\Models\Module;
use Baobab\Rendering\ActiveThemeResolver;
use Baobab\Themes\Actions\ActivateTheme;
use Baobab\Themes\Actions\DeactivateTheme;
use Baobab\Themes\Actions\PublishThemeAssets;
use Baobab\Themes\Exceptions\NotAThemeException;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    config(['baobab.modules.paths' => ['local' => [fixtureModulesPath('local/*')]]]);

    foreach (glob(public_path('baobab/tokens-*.css')) ?: [] as $file) {
        File::delete($file);
    }
});

afterEach(function () {
    removeThemeLink('acme-theme');
    removeThemeLink('acme-theme-assets');
});

it('deactivates the active theme and leaves the site with none', function () {
    app(InstallModule::class)('acme/theme');
    app(ActivateTheme::class)('acme/theme');

    app(DeactivateTheme::class)('acme/theme');

    expect(Module::where('name', 'acme/theme')->value('status'))->toBe('inactive')
        ->and(app(ActiveThemeResolver::class)->current())->toBeNull();
});

it('refuses a module that is not a theme', function () {
    // Sans cette garde, `baobab.system.themes.manage` — la seule permission
    // qui gouverne l'écran des thèmes — désactiverait n'importe quel module du
    // site sans jamais passer par `baobab.system.modules.manage`.
    app(InstallModule::class)('acme/blog');

    expect(fn () => app(DeactivateTheme::class)('acme/blog'))
        ->toThrow(NotAThemeException::class);

    expect(Module::where('name', 'acme/blog')->value('status'))->not->toBe('inactive');
});

it('leaves the published assets in place, so the theme stays previewable', function () {
    app(InstallModule::class)('acme/theme-assets');
    app(ActivateTheme::class)('acme/theme-assets');
    $link = public_path('themes/'.PublishThemeAssets::slugFor(
        Module::where('name', 'acme/theme-assets')->firstOrFail()
    ));

    expect(File::exists($link))->toBeTrue();

    app(DeactivateTheme::class)('acme/theme-assets');

    // La symétrie avec `ActivateTheme`, qui publie, serait trompeuse : la
    // jonction sert aussi la prévisualisation d'un thème inactif (n° 125).
    expect(File::exists($link))->toBeTrue();
});

it('recompiles the design tokens artifact, so a themeless site stops serving the departed theme colours', function () {
    app(InstallModule::class)('acme/theme-assets');
    app(ActivateTheme::class)('acme/theme-assets');

    $withTheme = glob(public_path('baobab/tokens-*.css')) ?: [];
    expect($withTheme)->toHaveCount(1)
        ->and(File::get($withTheme[0]))->toContain('#1b7f5a');

    app(DeactivateTheme::class)('acme/theme-assets');

    // `<x-baobab::design-tokens />` sert le fichier `tokens-*.css` trouvé sur
    // disque quel que soit son contenu : sans recompilation, un site revenu au
    // rendu de repli continuerait d'afficher la couleur d'un thème parti.
    $withoutTheme = glob(public_path('baobab/tokens-*.css')) ?: [];
    expect($withoutTheme)->toHaveCount(1)
        ->and($withoutTheme)->not->toBe($withTheme)
        ->and(File::get($withoutTheme[0]))->not->toContain('#1b7f5a');
});
