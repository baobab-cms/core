<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

/**
 * `ThemeMakeCommand` calcule `base_path("themes/{dirSlug}")` lui-même — pas
 * de configuration injectable, contrairement à `ThemeGenerator` (testé
 * séparément via `generatedThemePath()`). On écrit donc réellement sous
 * `themes/`, avec un slug dédié aux tests, nettoyé dans `afterEach()`.
 * `themes/` est gitignoré (contenu local de banc d'essai, spec 17 §1).
 */
function themeMakeCommandDir(): string
{
    return base_path('themes/pest-theme-make-test');
}

function writeThemeMakeCommandBlueprint(array $blueprint): void
{
    File::ensureDirectoryExists(themeMakeCommandDir());
    File::put(themeMakeCommandDir().'/theme.json', (string) json_encode($blueprint));
}

beforeEach(function () {
    File::deleteDirectory(themeMakeCommandDir());
});

afterEach(function () {
    File::deleteDirectory(themeMakeCommandDir());
});

it('fails clearly when no theme.json exists yet', function () {
    $exitCode = Artisan::call('baobab:make:theme', ['name' => 'acme/pest-theme-make-test']);

    expect($exitCode)->toBe(1)
        ->and(Artisan::output())->toContain('No theme.json found');
});

it('fails and reports validation errors for an invalid blueprint', function () {
    writeThemeMakeCommandBlueprint(['name' => 'Pest Theme']); // slug manquant, requis par le schéma

    $exitCode = Artisan::call('baobab:make:theme', ['name' => 'acme/pest-theme-make-test']);

    expect($exitCode)->toBe(1)
        ->and(Artisan::output())->toContain('Blueprint de thème invalide');
});

it('generates a valid theme end to end and reports success', function () {
    writeThemeMakeCommandBlueprint([
        'name' => 'Pest Theme',
        'slug' => 'pest-theme-make-test',
    ]);

    $exitCode = Artisan::call('baobab:make:theme', ['name' => 'acme/pest-theme-make-test']);

    expect($exitCode)->toBe(0)
        ->and(Artisan::output())->toContain('generated at');

    $dir = themeMakeCommandDir();

    expect(File::isFile("{$dir}/module.json"))->toBeTrue()
        ->and(File::isFile("{$dir}/screenshot.png"))->toBeTrue()
        ->and(File::isFile("{$dir}/resources/views/layouts/app.blade.php"))->toBeTrue()
        ->and(File::isFile("{$dir}/resources/views/templates/index.blade.php"))->toBeTrue();

    /** @var array<string, mixed> $manifest */
    $manifest = json_decode((string) File::get("{$dir}/module.json"), associative: true);

    expect($manifest['name'])->toBe('acme/pest-theme-make-test');
});
