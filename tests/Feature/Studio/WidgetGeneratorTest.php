<?php

use Baobab\Actions\Modules\ActivateModule;
use Baobab\Actions\Modules\InstallModule;
use Baobab\BaobabServiceProvider;
use Baobab\Studio\Blueprint\ModuleBlueprint;
use Baobab\Studio\Generator\ModuleGenerator;
use Baobab\Widgets\Actions\ResolveWidgetZone;
use Baobab\Widgets\Models\WidgetInstance;
use Illuminate\Support\Facades\File;

/**
 * Vendor `garage-widgets/fleet` distinct des autres suites Studio — voir
 * docblock de `ApiCrudGeneratorTest` pour la collision de namespace PHP que
 * ceci évite (cette suite installe+active réellement son module).
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

/**
 * Contrairement aux écouteurs de hooks (A3a, squelette vide à compléter à la
 * main), `settingsSchema()` est entièrement dérivable des `settings_fields`
 * du blueprint : la classe générée est donc réellement fonctionnelle sans
 * aucune étape manuelle — ce test le vérifie bout-en-bout via le vrai
 * pipeline de rendu (`ResolveWidgetZone`), pas seulement la présence des
 * fichiers générés.
 */
it('wires a generated widget into the real WidgetRegistry, resolved and rendered by ResolveWidgetZone', function () {
    $blueprint = ModuleBlueprint::fromJson(moduleBlueprintJson([
        'identity' => ['name' => 'garage-widgets/fleet', 'title' => 'Fleet', 'version' => '1.0.0', 'type' => 'module'],
        'widgets' => [[
            'key' => 'fleet.status-board',
            'label' => 'Tableau de flotte',
            'class_name' => 'StatusBoard',
            'settings_fields' => [
                ['key' => 'title', 'type' => 'text', 'label' => 'Titre', 'default' => ''],
            ],
        ]],
    ]));

    app(ModuleGenerator::class)($blueprint);

    $moduleDir = app(ModuleGenerator::class)->moduleDir('garage-widgets/fleet');

    expect(File::isFile("{$moduleDir}/src/Widgets/StatusBoard.php"))->toBeTrue()
        ->and(File::isFile("{$moduleDir}/resources/views/widgets/status-board.blade.php"))->toBeTrue();

    app(InstallModule::class)('garage-widgets/fleet');
    app(ActivateModule::class)('garage-widgets/fleet');

    $provider = app()->getProvider(BaobabServiceProvider::class);
    (new ReflectionMethod($provider, 'bootstrapActiveModules'))->invoke($provider);

    WidgetInstance::create([
        'zone_key' => 'sidebar',
        'widget_key' => 'fleet.status-board',
        'settings' => ['title' => 'Ma flotte'],
    ]);

    $rendered = app(ResolveWidgetZone::class)('sidebar');

    expect($rendered)->toHaveCount(1)
        ->and($rendered[0]['widget_key'])->toBe('fleet.status-board')
        ->and($rendered[0]['view'])->toBe('fleet::widgets.status-board')
        ->and($rendered[0]['data'])->toBe(['title' => 'Ma flotte']);
});

it('does not generate any file, and no widgets key in module.json, when none is declared', function () {
    $blueprint = ModuleBlueprint::fromJson(moduleBlueprintJson([
        'identity' => ['name' => 'garage-widgets/fleet', 'title' => 'Fleet', 'version' => '1.0.0', 'type' => 'module'],
    ]));

    app(ModuleGenerator::class)($blueprint);

    $moduleDir = app(ModuleGenerator::class)->moduleDir('garage-widgets/fleet');

    expect(File::isDirectory("{$moduleDir}/src/Widgets"))->toBeFalse();

    $manifest = json_decode(File::get("{$moduleDir}/module.json"), associative: true);

    expect($manifest)->not->toHaveKey('widgets');
});
