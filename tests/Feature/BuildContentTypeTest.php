<?php

use Baobab\ContentTypes\Actions\BuildContentType;
use Baobab\ContentTypes\Exceptions\DuplicateContentTypeException;
use Baobab\Facades\Hook;
use Baobab\Modules\Models\Module;
use Baobab\Modules\ModuleAutoloader;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    File::deleteDirectory(generatedModulesPath());
    config(['baobab.content_types.modules_path' => generatedModulesPath()]);
    config(['baobab.modules.paths' => ['local' => [generatedModulesPath().'/*']]]);
});

afterEach(function () {
    File::deleteDirectory(generatedModulesPath());
});

it('builds a content type end to end: table created, module active, permissions real, hook fired', function () {
    $received = null;
    Hook::listen('baobab.content_type.created', function ($contentType) use (&$received): void {
        $received = $contentType;
    });

    $contentType = app(BuildContentType::class)(carBlueprintJson());

    expect($contentType->module_id)->not->toBeNull()
        ->and(Schema::hasTable('ct_cars'))->toBeTrue();

    $module = Module::findOrFail($contentType->module_id);
    expect($module->status)->toBe('active')
        ->and($module->name)->toBe('content-types/cars');

    expect(Permission::where('name', 'content.car.view')->where('guard_name', 'baobab')->exists())->toBeTrue();

    expect($received)->not->toBeNull();

    // Prouve que le trou d'autoload M1 comblé en 1b s'intègre réellement
    // avec la sortie du générateur, pas seulement en isolation
    // (ModuleAutoloaderTest.php) : la classe Model générée est chargeable
    // une fois le mapping enregistré, sans avoir été require-ée à la main.
    app(ModuleAutoloader::class)->registerFor($module);
    expect(class_exists('Modules\\Car\\Models\\Car'))->toBeTrue();
});

it('runs a migration with real field columns end to end', function () {
    // Clé distincte de carBlueprintJson()'s "Car" par défaut : une fois
    // Modules\Car\Models\Car chargée en mémoire par un autre test du même
    // fichier, PHP ne peut plus redéfinir cette classe même si le fichier
    // généré change sur disque (contrainte du langage, pas un bug du
    // générateur) — même piège que le cache négatif de Composer croisé en 1b.
    $contentType = app(BuildContentType::class)(carBlueprintJson([
        'key' => 'SportsCar',
        'label' => ['singular' => 'Voiture de sport', 'plural' => 'Voitures de sport'],
        'fields' => [
            ['key' => 'brand', 'type' => 'text'],
            ['key' => 'is_featured', 'type' => 'boolean'],
        ],
    ]));

    expect(Schema::hasColumns('ct_sports_cars', ['brand', 'is_featured']))->toBeTrue();

    $module = Module::findOrFail($contentType->module_id);
    app(ModuleAutoloader::class)->registerFor($module);

    // Dérivé du manifest généré plutôt qu'écrit en dur : PHPStan ne peut pas
    // constant-plier la classe (elle n'existe qu'à partir de cette ligne),
    // donc `new $carClass(...)` reste analysable sans class.notFound.
    $namespace = (string) array_key_first($module->manifest['autoload']['psr-4']);
    $carClass = rtrim($namespace, '\\')."\\Models\\{$contentType->key}";

    /** @var Model $car */
    $car = new $carClass([
        'brand' => 'Peugeot',
        'is_featured' => true,
    ]);
    $car->save();

    $car = $car->fresh();

    if ($car === null) {
        throw new RuntimeException('Expected the newly saved SportsCar to be refetchable.');
    }

    expect($car->getAttribute('brand'))->toBe('Peugeot')
        ->and($car->getAttribute('is_featured'))->toBeTrue();
});

it('runs a real belongsTo relation end to end', function () {
    $manufacturer = app(BuildContentType::class)((string) json_encode([
        'key' => 'Manufacturer',
        'label' => ['singular' => 'Fabricant', 'plural' => 'Fabricants'],
    ]));

    $manufacturerModule = Module::findOrFail($manufacturer->module_id);
    app(ModuleAutoloader::class)->registerFor($manufacturerModule);
    $manufacturerClass = $manufacturer->modelClass();
    /** @var Model $manufacturerRow */
    $manufacturerRow = new $manufacturerClass;
    $manufacturerRow->save();

    $vehicle = app(BuildContentType::class)((string) json_encode([
        'key' => 'Vehicle',
        'label' => ['singular' => 'Véhicule', 'plural' => 'Véhicules'],
        'relations' => [['key' => 'manufacturer', 'type' => 'one_to_many', 'target' => 'Manufacturer']],
    ]));

    $vehicleModule = Module::findOrFail($vehicle->module_id);
    app(ModuleAutoloader::class)->registerFor($vehicleModule);
    $vehicleClass = $vehicle->modelClass();

    /** @var Model $vehicleRow */
    $vehicleRow = new $vehicleClass(['manufacturer_id' => $manufacturerRow->getKey()]);
    $vehicleRow->save();
    $vehicleRow = $vehicleRow->fresh(['manufacturer']);

    if ($vehicleRow === null) {
        throw new RuntimeException('Expected the newly saved Vehicle to be refetchable.');
    }

    $relatedManufacturer = $vehicleRow->getRelationValue('manufacturer');

    if (! $relatedManufacturer instanceof Model) {
        throw new RuntimeException('Expected the Vehicle to have a loaded manufacturer relation.');
    }

    expect($relatedManufacturer->getKey())->toBe($manufacturerRow->getKey());
});

it('runs a real belongsToMany relation end to end via the generated pivot table', function () {
    $accessory = app(BuildContentType::class)((string) json_encode([
        'key' => 'Accessory',
        'label' => ['singular' => 'Accessoire', 'plural' => 'Accessoires'],
    ]));

    $accessoryModule = Module::findOrFail($accessory->module_id);
    app(ModuleAutoloader::class)->registerFor($accessoryModule);
    $accessoryClass = $accessory->modelClass();
    /** @var Model $accessoryRow */
    $accessoryRow = new $accessoryClass;
    $accessoryRow->save();

    $gadget = app(BuildContentType::class)((string) json_encode([
        'key' => 'Gadget',
        'label' => ['singular' => 'Gadget', 'plural' => 'Gadgets'],
        'relations' => [['key' => 'accessories', 'type' => 'many_to_many', 'target' => 'Accessory']],
    ]));

    expect(Schema::hasTable('ct_accessory_gadget'))->toBeTrue();

    $gadgetModule = Module::findOrFail($gadget->module_id);
    app(ModuleAutoloader::class)->registerFor($gadgetModule);
    $gadgetClass = $gadget->modelClass();

    /** @var Model $gadgetRow */
    $gadgetRow = new $gadgetClass;
    $gadgetRow->save();

    // Nom de méthode dérivé du blueprint plutôt qu'écrit en dur : la classe
    // et sa méthode accessories() n'existent qu'à partir de cette ligne, et
    // PHPStan résout quand même un $var = 'literal' juste au-dessus d'un
    // appel dynamique — seule une valeur véritablement non constante (issue
    // ici du blueprint stocké en base) évite le class.notFound.
    /** @var array<int, array{key: string}> $relations */
    $relations = $gadget->blueprint['relations'];
    $relationMethod = $relations[0]['key'];
    $gadgetRow->{$relationMethod}()->attach($accessoryRow->getKey());

    $gadgetRow = $gadgetRow->fresh(['accessories']);

    if ($gadgetRow === null) {
        throw new RuntimeException('Expected the newly saved Gadget to be refetchable.');
    }

    /** @var Collection<int, Model> $relatedAccessories */
    $relatedAccessories = $gadgetRow->getRelationValue('accessories');

    expect($relatedAccessories)->toHaveCount(1)
        ->and($relatedAccessories->first()?->getKey())->toBe($accessoryRow->getKey());
});

it('refuses to build a content type with a duplicate key', function () {
    app(BuildContentType::class)(carBlueprintJson());

    expect(fn () => app(BuildContentType::class)(carBlueprintJson()))
        ->toThrow(DuplicateContentTypeException::class);
});
