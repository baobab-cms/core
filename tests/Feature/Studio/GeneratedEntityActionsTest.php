<?php

use Baobab\Studio\Blueprint\ModuleBlueprint;
use Baobab\Studio\Generator\ModuleGenerator;
use Illuminate\Support\Facades\File;

/**
 * Suivi n° 141 — le CRUD généré court-circuitait la couche d'actions : les deux
 * contrôleurs écrivaient en base en direct, ce que le principe 4 interdit et que
 * la spec-modules §0 étend explicitement au Studio lui-même.
 */
beforeEach(function () {
    File::deleteDirectory(generatedModulesPath());
    config(['baobab.studio.modules_path' => generatedModulesPath()]);
});

afterEach(function () {
    File::deleteDirectory(generatedModulesPath());
});

function generatedActionsModuleDir(): string
{
    return generatedModulesPath().'/garage-fleet';
}

function generateFleetWithSurfaces(bool $admin = true, bool $api = true): void
{
    $blueprint = ModuleBlueprint::fromJson(moduleBlueprintJson([
        'entities' => [[
            'key' => 'Car',
            'table' => 'cars',
            'fields' => [['key' => 'brand', 'type' => 'text', 'required' => true]],
            'relations' => [],
            'routes' => ['admin' => $admin, 'front' => false, 'api' => $api],
        ]],
    ]));

    app(ModuleGenerator::class)($blueprint);
}

it('generates one save action and one delete action per entity', function () {
    generateFleetWithSurfaces();

    $save = (string) file_get_contents(generatedActionsModuleDir().'/src/Actions/SaveCar.php');
    $delete = (string) file_get_contents(generatedActionsModuleDir().'/src/Actions/DeleteCar.php');

    expect($save)->toContain('namespace Garage\Fleet\Actions;')
        ->and($save)->toContain('final class SaveCar')
        ->and($save)->toContain('public function __invoke(array $data, ?Car $car = null): Car')
        ->and($delete)->toContain('final class DeleteCar')
        ->and($delete)->toContain('public function __invoke(Car $car): void');
});

it('follows the Content Type actions it is modelled on', function () {
    generateFleetWithSurfaces();

    $save = (string) file_get_contents(generatedActionsModuleDir().'/src/Actions/SaveCar.php');

    // Patron `SaveContentEntry` : audit, hooks encadrant l'écriture, drapeau
    // distinguant création et mise à jour.
    expect($save)->toContain('private readonly AuditLogger $audit')
        ->and($save)->toContain("Hook::action('fleet.car.saving'")
        ->and($save)->toContain("Hook::action('fleet.car.saved'")
        ->and($save)->toContain("\$isNew ? 'fleet.car.created' : 'fleet.car.updated'");
});

it('leaves authorization out of the actions, where the policy holds it', function () {
    generateFleetWithSurfaces();

    $save = (string) file_get_contents(generatedActionsModuleDir().'/src/Actions/SaveCar.php');
    $delete = (string) file_get_contents(generatedActionsModuleDir().'/src/Actions/DeleteCar.php');

    // L'autorisation reste dans la policy, interrogée par les contrôleurs —
    // l'interdit « logique own/any hors des policies » vaut aussi pour le généré.
    expect($save)->not->toContain('abort_unless')
        ->and($save)->not->toContain('->can(')
        ->and($delete)->not->toContain('abort_unless');
});

it('reduces the admin controller to an adapter', function () {
    generateFleetWithSurfaces();

    $controller = (string) file_get_contents(generatedActionsModuleDir().'/src/Http/Controllers/Admin/CarController.php');

    expect($controller)->toContain('app(SaveCar::class)($request->validated())')
        ->and($controller)->toContain('app(SaveCar::class)($request->validated(), $car)')
        ->and($controller)->toContain('app(DeleteCar::class)($car)')
        // Plus une seule écriture en direct : c'était tout le défaut.
        ->and($controller)->not->toContain('Car::create(')
        ->and($controller)->not->toContain('$car->update(')
        ->and($controller)->not->toContain('$car->delete()')
        // L'autorisation, elle, reste bien ici.
        ->and($controller)->toContain('abort_unless');
});

it('routes the API controller through the very same actions', function () {
    generateFleetWithSurfaces();

    $controller = (string) file_get_contents(generatedActionsModuleDir().'/src/Http/Controllers/Api/CarController.php');

    // Le point du n° 141 : une règle métier ajoutée à SaveCar vaut pour les deux
    // surfaces, au lieu d'être à récrire de chaque côté.
    expect($controller)->toContain('app(SaveCar::class)($request->validated())')
        ->and($controller)->toContain('app(SaveCar::class)($request->validated(), $car)')
        ->and($controller)->toContain('app(DeleteCar::class)($car)')
        ->and($controller)->not->toContain('Car::create(')
        ->and($controller)->not->toContain('$car->update(')
        ->and($controller)->not->toContain('$car->delete()');
});

it('generates the actions even for an entity that exposes no surface at all', function () {
    generateFleetWithSurfaces(admin: false, api: false);

    // Une entité sans route reste écrite par une commande, un seeder ou un
    // module tiers : l'Action est le point d'entrée qui existe toujours.
    expect(File::isFile(generatedActionsModuleDir().'/src/Actions/SaveCar.php'))->toBeTrue()
        ->and(File::isFile(generatedActionsModuleDir().'/src/Actions/DeleteCar.php'))->toBeTrue()
        ->and(File::isFile(generatedActionsModuleDir().'/src/Http/Controllers/Admin/CarController.php'))->toBeFalse();
});

it('generates a distinct pair of actions per entity', function () {
    $blueprint = ModuleBlueprint::fromJson(moduleBlueprintJson([
        'entities' => [
            [
                'key' => 'Car',
                'table' => 'cars',
                'fields' => [['key' => 'brand', 'type' => 'text', 'required' => true]],
                'relations' => [],
                'routes' => ['admin' => true, 'front' => false, 'api' => false],
            ],
            [
                'key' => 'Driver',
                'table' => 'drivers',
                'fields' => [['key' => 'name', 'type' => 'text', 'required' => true]],
                'relations' => [],
                'routes' => ['admin' => true, 'front' => false, 'api' => false],
            ],
        ],
    ]));

    app(ModuleGenerator::class)($blueprint);

    $driver = (string) file_get_contents(generatedActionsModuleDir().'/src/Actions/SaveDriver.php');

    expect(File::isFile(generatedActionsModuleDir().'/src/Actions/SaveCar.php'))->toBeTrue()
        ->and($driver)->toContain('final class SaveDriver')
        ->and($driver)->toContain("Hook::action('fleet.driver.saved'");
});
