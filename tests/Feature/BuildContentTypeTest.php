<?php

use Baobab\ContentTypes\Actions\BuildContentType;
use Baobab\ContentTypes\Exceptions\DuplicateContentTypeException;
use Baobab\Facades\Hook;
use Baobab\Modules\Models\Module;
use Baobab\Modules\ModuleAutoloader;
use Illuminate\Database\Eloquent\Model;
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

    expect(Permission::where('name', 'content_types.car.view')->where('guard_name', 'baobab')->exists())->toBeTrue();

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

it('refuses to build a content type with a duplicate key', function () {
    app(BuildContentType::class)(carBlueprintJson());

    expect(fn () => app(BuildContentType::class)(carBlueprintJson()))
        ->toThrow(DuplicateContentTypeException::class);
});
