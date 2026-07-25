<?php

use Baobab\Branding\Actions\SyncThemeFonts;
use Baobab\Branding\Models\Font;
use Baobab\Modules\Models\Module;
use Illuminate\Support\Facades\File;

function themeFontFixturePath(): string
{
    return sys_get_temp_dir().'/baobab-test-theme-fonts';
}

function makeThemeModuleWithFonts(array $fonts): Module
{
    $path = themeFontFixturePath();
    File::ensureDirectoryExists($path.'/assets/fonts');
    File::put($path.'/assets/fonts/fraunces-var.woff2', 'wOF2'.random_bytes(16));

    return Module::create([
        'name' => 'acme/font-theme',
        'title' => 'Font Theme',
        'type' => 'theme',
        'version' => '1.0.0',
        'provider' => 'Acme\\FontTheme\\Providers\\ThemeServiceProvider',
        'source' => 'local',
        'path' => $path,
        'manifest' => ['fonts' => $fonts],
        'status' => 'active',
    ]);
}

beforeEach(function () {
    File::deleteDirectory(themeFontFixturePath());
});

afterEach(function () {
    File::deleteDirectory(themeFontFixturePath());
    resetFontsRegistryStorage();
});

it('registers a theme-declared font in the registry and copies its file into central storage', function () {
    $theme = makeThemeModuleWithFonts([
        ['family' => 'Fraunces', 'files' => ['variable' => 'assets/fonts/fraunces-var.woff2'], 'license' => 'OFL-1.1'],
    ]);

    app(SyncThemeFonts::class)($theme);

    $font = Font::where('slug', 'fraunces')->first();

    expect($font)->not->toBeNull()
        ->and($font->source)->toBe(Font::SOURCE_THEME)
        ->and($font->theme_module_id)->toBe($theme->id)
        ->and($font->is_variable)->toBeTrue()
        ->and(is_file(storage_path('app/baobab/fonts/fraunces/fraunces-var.woff2')))->toBeTrue();
});

it('does nothing when the theme declares no fonts', function () {
    $theme = makeThemeModuleWithFonts([]);

    app(SyncThemeFonts::class)($theme);

    expect(Font::where('theme_module_id', $theme->id)->count())->toBe(0);
});
