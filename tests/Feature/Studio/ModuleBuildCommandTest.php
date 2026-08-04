<?php

use Baobab\Studio\Generator\ModuleGenerator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

/**
 * Vendor `garage-build/fleet` distinct des autres suites Studio — voir
 * docblock de `ApiCrudGeneratorTest` pour la collision de namespace PHP que
 * ceci évite.
 */
beforeEach(function () {
    File::deleteDirectory(generatedModulesPath());
    config(['baobab.studio.modules_path' => generatedModulesPath()]);
    config(['baobab.content_types.modules_path' => generatedModulesPath()]);
    config(['baobab.modules.paths' => ['local' => [generatedModulesPath().'/*']]]);
});

afterEach(function () {
    File::deleteDirectory(generatedModulesPath());
});

function moduleBlueprintFixturePath(): string
{
    return sys_get_temp_dir().'/baobab-test-module-blueprint.json';
}

it('module:build generates a module on disk from a blueprint file', function () {
    File::put(moduleBlueprintFixturePath(), moduleBlueprintJson([
        'identity' => ['name' => 'garage-build/fleet', 'title' => 'Fleet', 'version' => '1.0.0', 'type' => 'module'],
    ]));

    $exitCode = Artisan::call('module:build', ['path' => moduleBlueprintFixturePath()]);
    $output = Artisan::output();

    $moduleDir = app(ModuleGenerator::class)->moduleDir('garage-build/fleet');

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('garage-build/fleet')
        ->and(File::isFile("{$moduleDir}/module.json"))->toBeTrue()
        ->and(File::isFile("{$moduleDir}/src/Models/Car.php"))->toBeTrue();

    File::delete(moduleBlueprintFixturePath());
});

it('module:build fails cleanly when the file does not exist', function () {
    $exitCode = Artisan::call('module:build', ['path' => sys_get_temp_dir().'/does-not-exist.json']);
    $output = Artisan::output();

    expect($exitCode)->toBe(1)
        ->and($output)->toContain('introuvable');
});

it('module:build fails cleanly on an invalid blueprint', function () {
    File::put(moduleBlueprintFixturePath(), (string) json_encode(['identity' => ['name' => 'garage-build/fleet']]));

    $exitCode = Artisan::call('module:build', ['path' => moduleBlueprintFixturePath()]);
    $output = Artisan::output();

    expect($exitCode)->toBe(1)
        ->and($output)->toContain('Blueprint de module invalide');

    File::delete(moduleBlueprintFixturePath());
});
