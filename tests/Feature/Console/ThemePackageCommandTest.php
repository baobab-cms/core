<?php

use Baobab\Themes\Generator\ThemeGenerator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

function themePackageThemeDir(): string
{
    return base_path('themes/pest-theme-package-test');
}

function themePackageOutputPath(): string
{
    return sys_get_temp_dir().'/baobab-test-theme-package.zip';
}

beforeEach(function () {
    File::deleteDirectory(themePackageThemeDir());
    File::delete(themePackageOutputPath());
    config(['baobab.modules.paths' => ['local' => [base_path('themes/*')]]]);
});

afterEach(function () {
    File::deleteDirectory(themePackageThemeDir());
    File::delete(themePackageOutputPath());
});

it('packages a valid theme into a zip archive, excluding the generation state file', function () {
    app(ThemeGenerator::class)('acme/pest-theme-package-test', themePackageThemeDir(), [
        'name' => 'Pest Theme',
        'slug' => 'pest-theme-package-test',
    ]);

    expect(File::isFile(themePackageThemeDir().'/.baobab-checksums.json'))->toBeTrue();

    $exitCode = Artisan::call('baobab:theme:package', [
        'name' => 'acme/pest-theme-package-test',
        '--output' => themePackageOutputPath(),
    ]);

    expect($exitCode)->toBe(0)
        ->and(Artisan::output())->toContain('packaged at')
        ->and(File::isFile(themePackageOutputPath()))->toBeTrue();

    $zip = new ZipArchive;
    $zip->open(themePackageOutputPath());

    expect($zip->locateName('module.json'))->not->toBeFalse()
        ->and($zip->locateName('resources/views/layouts/app.blade.php'))->not->toBeFalse()
        ->and($zip->locateName('.baobab-checksums.json'))->toBeFalse();

    $zip->close();
});

it('fails without packaging when the theme has blocking violations', function () {
    File::ensureDirectoryExists(themePackageThemeDir());
    File::put(themePackageThemeDir().'/module.json', (string) json_encode([
        'name' => 'acme/pest-theme-package-test',
        'title' => 'Pest Theme',
        'version' => '1.0.0',
        'type' => 'theme',
        'provider' => 'Acme\\PestThemePackageTest\\Providers\\ThemeServiceProvider',
        'theme' => ['parent' => null],
    ]));

    $exitCode = Artisan::call('baobab:theme:package', [
        'name' => 'acme/pest-theme-package-test',
        '--output' => themePackageOutputPath(),
    ]);

    expect($exitCode)->toBe(1)
        ->and(File::isFile(themePackageOutputPath()))->toBeFalse();
});

it('fails clearly when the theme cannot be discovered', function () {
    $exitCode = Artisan::call('baobab:theme:package', ['name' => 'acme/does-not-exist']);

    expect($exitCode)->toBe(1)
        ->and(Artisan::output())->toContain('not found');
});
