<?php

use Baobab\Actions\Modules\InstallModule;
use Baobab\Modules\Models\Module;
use Baobab\Themes\ThemeViewRegistrar;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;

beforeEach(function () {
    config(['baobab.modules.paths' => ['local' => [fixtureModulesPath('local/*')]]]);
});

it('registers nothing when no theme is active', function () {
    app(ThemeViewRegistrar::class)->registerFor(null);

    expect(View::exists('theme::templates.single'))->toBeFalse();
});

it('resolves the theme own template and does not see a namespace when only the theme is registered', function () {
    app(InstallModule::class)('acme/theme');
    $theme = Module::where('name', 'acme/theme')->firstOrFail();

    app(ThemeViewRegistrar::class)->registerFor($theme);

    expect(View::exists('theme::templates.single'))->toBeTrue()
        ->and(trim(view('theme::templates.single')->render()))->toBe('<p>acme-theme single</p>');
});

it('falls back to the parent theme for a template the child does not provide', function () {
    app(InstallModule::class)('acme/theme');
    app(InstallModule::class)('acme/theme-child');
    $child = Module::where('name', 'acme/theme-child')->firstOrFail();

    app(ThemeViewRegistrar::class)->registerFor($child);

    // L'enfant ne fournit que single.blade.php.
    expect(trim(view('theme::templates.single')->render()))->toBe('<p>acme-theme-child single</p>')
        ->and(trim(view('theme::templates.index')->render()))->toBe('<p>acme-theme index</p>');
});

it('lets the active theme override a module view via the native Laravel vendor-view convention', function () {
    $basePath = sys_get_temp_dir().'/baobab-test-widgets-base';
    File::ensureDirectoryExists($basePath);
    File::put($basePath.'/widget.blade.php', 'base widget');

    app(InstallModule::class)('acme/theme');
    $theme = Module::where('name', 'acme/theme')->firstOrFail();

    // Le thème actif est enregistré en premier (patron ResolveActiveTheme,
    // avant que les providers de modules n'enregistrent leurs propres vues).
    app(ThemeViewRegistrar::class)->registerFor($theme);

    // Reproduit exactement ce que fait Illuminate\Support\ServiceProvider::loadViewsFrom()
    // pour le module dont la vue est surchargée : vérifie config('view.paths')
    // pour un override vendor avant d'ajouter le chemin propre au module.
    foreach ((array) config('view.paths') as $viewPath) {
        if (is_dir($vendorPath = $viewPath.'/vendor/widgets')) {
            View::addNamespace('widgets', $vendorPath);
        }
    }
    View::addNamespace('widgets', $basePath);

    expect(trim(view('widgets::widget')->render()))->toBe('<p>acme-theme override of widgets::widget</p>');

    File::deleteDirectory($basePath);
});
