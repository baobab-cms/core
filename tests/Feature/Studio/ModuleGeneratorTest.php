<?php

use Baobab\ContentTypes\Actions\BuildContentType;
use Baobab\ContentTypes\Exceptions\GeneratedFileConflictException;
use Baobab\Studio\Blueprint\ModuleBlueprint;
use Baobab\Studio\Generator\ModuleGenerator;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    File::deleteDirectory(generatedModulesPath());
    config(['baobab.studio.modules_path' => generatedModulesPath()]);
    config(['baobab.content_types.modules_path' => generatedModulesPath()]);
    config(['baobab.modules.paths' => ['local' => [generatedModulesPath().'/*']]]);
});

afterEach(function () {
    File::deleteDirectory(generatedModulesPath());
});

it('generates a complete module tree for a single-entity blueprint', function () {
    $blueprint = ModuleBlueprint::fromJson(moduleBlueprintJson());

    $moduleName = app(ModuleGenerator::class)($blueprint);

    expect($moduleName)->toBe('garage/fleet');

    $moduleDir = generatedModulesPath().'/garage-fleet';

    expect(File::isFile($moduleDir.'/module.json'))->toBeTrue()
        ->and(File::isFile($moduleDir.'/src/Models/Car.php'))->toBeTrue()
        ->and(File::isFile($moduleDir.'/src/Policies/CarPolicy.php'))->toBeTrue()
        ->and(File::isFile($moduleDir.'/src/Providers/FleetServiceProvider.php'))->toBeTrue()
        ->and(File::isFile($moduleDir.'/.baobab-checksums.json'))->toBeTrue();

    $migrationFiles = File::glob($moduleDir.'/database/migrations/*.php');
    expect($migrationFiles)->toHaveCount(1)
        ->and(file_get_contents($migrationFiles[0]))->toContain("Schema::create('cars'");

    /** @var array<string, mixed> $manifest */
    $manifest = json_decode((string) file_get_contents($moduleDir.'/module.json'), associative: true);

    expect($manifest['name'])->toBe('garage/fleet')
        ->and($manifest['type'])->toBe('module')
        ->and($manifest['provider'])->toBe('Garage\\Fleet\\Providers\\FleetServiceProvider')
        ->and($manifest['autoload']['psr-4'])->toBe(['Garage\\Fleet\\' => 'src/'])
        ->and(collect((array) $manifest['permissions'])->pluck('key')->all())->toBe([
            'fleet.cars.view',
            'fleet.cars.create',
            'fleet.cars.update',
            'fleet.cars.delete',
        ]);
});

it('omits permissions from auto CRUD when disabled, keeping only custom ones', function () {
    $blueprint = ModuleBlueprint::fromJson(moduleBlueprintJson([
        // Sans surface autorisée : couper `auto_crud` en laissant l'admin
        // actif produirait un module que personne ne peut utiliser, et le
        // blueprint est desormais refuse a la generation pour cette raison.
        'entities' => [['key' => 'Car', 'table' => 'cars', 'routes' => ['admin' => false, 'front' => false, 'api' => false]]],
        'permissions' => [
            'auto_crud' => false,
            'custom' => [['entity' => 'Car', 'key' => 'publish', 'label' => 'Publier']],
        ],
    ]));

    app(ModuleGenerator::class)($blueprint);

    /** @var array<string, mixed> $manifest */
    $manifest = json_decode((string) file_get_contents(generatedModulesPath().'/garage-fleet/module.json'), associative: true);

    expect(collect((array) $manifest['permissions'])->pluck('key')->all())->toBe(['fleet.cars.publish']);

    $policyContents = (string) file_get_contents(generatedModulesPath().'/garage-fleet/src/Policies/CarPolicy.php');
    expect($policyContents)->toContain('public function publish(User $user, Car $car): bool')
        ->and($policyContents)->toContain("return \$user->can('fleet.cars.publish');");
});

it('enables SoftDeletes when the entity option is set', function () {
    $blueprint = ModuleBlueprint::fromJson(moduleBlueprintJson([
        'entities' => [['key' => 'Car', 'table' => 'cars', 'options' => ['soft_deletes' => true]]],
    ]));

    app(ModuleGenerator::class)($blueprint);

    $moduleDir = generatedModulesPath().'/garage-fleet';
    $migrationFiles = File::glob($moduleDir.'/database/migrations/*.php');

    expect(file_get_contents($migrationFiles[0]))->toContain('$table->softDeletes();');

    $modelContents = (string) file_get_contents($moduleDir.'/src/Models/Car.php');
    expect($modelContents)->toContain('use Illuminate\\Database\\Eloquent\\SoftDeletes;')
        ->and($modelContents)->toContain('use SoftDeletes;');
});

it('switches to a UUID primary key when the entity option is set', function () {
    $blueprint = ModuleBlueprint::fromJson(moduleBlueprintJson([
        'entities' => [['key' => 'Car', 'table' => 'cars', 'options' => ['uuid' => true]]],
    ]));

    app(ModuleGenerator::class)($blueprint);

    $moduleDir = generatedModulesPath().'/garage-fleet';
    $migrationFiles = File::glob($moduleDir.'/database/migrations/*.php');

    expect(file_get_contents($migrationFiles[0]))->toContain("\$table->uuid('id')->primary();")
        ->and(file_get_contents($migrationFiles[0]))->not->toContain('$table->id();');

    $modelContents = (string) file_get_contents($moduleDir.'/src/Models/Car.php');
    expect($modelContents)->toContain('use Illuminate\\Database\\Eloquent\\Concerns\\HasUuids;')
        ->and($modelContents)->toContain('use HasUuids;');
});

it('omits timestamps from the migration when disabled', function () {
    $blueprint = ModuleBlueprint::fromJson(moduleBlueprintJson([
        'entities' => [['key' => 'Car', 'table' => 'cars', 'options' => ['timestamps' => false]]],
    ]));

    app(ModuleGenerator::class)($blueprint);

    $migrationFiles = File::glob(generatedModulesPath().'/garage-fleet/database/migrations/*.php');
    expect(file_get_contents($migrationFiles[0]))->not->toContain('timestamps');
});

it('generates a real column, cast and fillable for a declared field', function () {
    $blueprint = ModuleBlueprint::fromJson(moduleBlueprintJson([
        'entities' => [[
            'key' => 'Car',
            'table' => 'cars',
            'fields' => [
                ['key' => 'brand', 'type' => 'text'],
                ['key' => 'specs', 'type' => 'json'],
            ],
        ]],
    ]));

    app(ModuleGenerator::class)($blueprint);

    $moduleDir = generatedModulesPath().'/garage-fleet';
    $migrationFiles = File::glob($moduleDir.'/database/migrations/*.php');
    expect(file_get_contents($migrationFiles[0]))->toContain("\$table->string('brand', 255);");

    $modelContents = (string) file_get_contents($moduleDir.'/src/Models/Car.php');
    expect($modelContents)->toContain("'brand',")
        ->and($modelContents)->toContain("'specs' => 'array',");
});

it('excludes a gallery field from the migration and model fillable (no own column)', function () {
    $blueprint = ModuleBlueprint::fromJson(moduleBlueprintJson([
        'entities' => [[
            'key' => 'Car',
            'table' => 'cars',
            'fields' => [
                ['key' => 'brand', 'type' => 'text'],
                ['key' => 'photos', 'type' => 'gallery'],
            ],
        ]],
    ]));

    app(ModuleGenerator::class)($blueprint);

    $moduleDir = generatedModulesPath().'/garage-fleet';
    $migrationFiles = File::glob($moduleDir.'/database/migrations/*.php');
    expect(file_get_contents($migrationFiles[0]))->not->toContain('photos');

    $modelContents = (string) file_get_contents($moduleDir.'/src/Models/Car.php');
    expect($modelContents)->not->toContain("'photos',");
});

it('generates a real FK column and belongsTo method for a relation to a sibling entity', function () {
    $blueprint = ModuleBlueprint::fromJson(moduleBlueprintJson([
        'entities' => [
            ['key' => 'Car', 'table' => 'cars', 'relations' => [
                ['key' => 'brand', 'type' => 'one_to_many', 'target' => 'entity:Brand'],
            ]],
            ['key' => 'Brand', 'table' => 'brands'],
        ],
    ]));

    app(ModuleGenerator::class)($blueprint);

    $moduleDir = generatedModulesPath().'/garage-fleet';
    $migrationFiles = File::glob($moduleDir.'/database/migrations/*.php');
    $migrationContents = collect($migrationFiles)->map(fn (string $path) => (string) file_get_contents($path))->implode("\n");
    expect($migrationContents)->toContain("\$table->foreignId('brand_id')->nullable()->constrained('brands')->restrictOnDelete();");

    $modelContents = (string) file_get_contents($moduleDir.'/src/Models/Car.php');
    expect($modelContents)->toContain('public function brand(): \Illuminate\Database\Eloquent\Relations\BelongsTo')
        ->and($modelContents)->toContain("return \$this->belongsTo(\\Garage\\Fleet\\Models\\Brand::class, 'brand_id');")
        ->and($modelContents)->toContain("'brand_id',");
});

it('generates a real FK column for a relation to an already-built content type', function () {
    app(BuildContentType::class)((string) json_encode([
        'key' => 'Brand',
        'label' => ['singular' => 'Marque', 'plural' => 'Marques'],
    ]));

    $blueprint = ModuleBlueprint::fromJson(moduleBlueprintJson([
        'entities' => [
            ['key' => 'Car', 'table' => 'cars', 'relations' => [
                ['key' => 'brand', 'type' => 'one_to_many', 'target' => 'content_type:Brand'],
            ]],
        ],
    ]));

    app(ModuleGenerator::class)($blueprint);

    $migrationFiles = File::glob(generatedModulesPath().'/garage-fleet/database/migrations/*.php');
    $migrationContents = collect($migrationFiles)->map(fn (string $path) => (string) file_get_contents($path))->implode("\n");
    expect($migrationContents)->toContain("\$table->foreignId('brand_id')->nullable()->constrained('ct_brands')->restrictOnDelete();");
});

it('generates a pivot migration (without ct_ prefix) and belongsToMany for a many_to_many relation between siblings', function () {
    $blueprint = ModuleBlueprint::fromJson(moduleBlueprintJson([
        'entities' => [
            ['key' => 'Car', 'table' => 'cars', 'relations' => [
                ['key' => 'options', 'type' => 'many_to_many', 'target' => 'entity:Option'],
            ]],
            ['key' => 'Option', 'table' => 'options'],
        ],
    ]));

    app(ModuleGenerator::class)($blueprint);

    $moduleDir = generatedModulesPath().'/garage-fleet';
    $migrationFiles = File::glob($moduleDir.'/database/migrations/*.php');
    expect($migrationFiles)->toHaveCount(3); // cars, options, and the car_option pivot

    $pivotFile = collect($migrationFiles)->first(fn (string $path) => str_contains($path, '_car_option_table'));
    expect($pivotFile)->not->toBeNull()
        ->and((string) file_get_contents($pivotFile))->toContain("Schema::create('car_option'");

    $modelContents = (string) file_get_contents($moduleDir.'/src/Models/Car.php');
    expect($modelContents)->toContain('public function options(): \Illuminate\Database\Eloquent\Relations\BelongsToMany');
});

it('omits the hooks block entirely from module.json when no hooks are declared', function () {
    $blueprint = ModuleBlueprint::fromJson(moduleBlueprintJson());

    app(ModuleGenerator::class)($blueprint);

    /** @var array<string, mixed> $manifest */
    $manifest = json_decode((string) file_get_contents(generatedModulesPath().'/garage-fleet/module.json'), associative: true);

    expect($manifest)->not->toHaveKey('hooks');
});

it('generates a hook listener skeleton and writes the hooks block to module.json', function () {
    $blueprint = ModuleBlueprint::fromJson(moduleBlueprintJson([
        'hooks' => [
            'emits' => ['garage.fleet.car.serviced'],
            'listens' => ['baobab.content.saved' => 'NotifyFleetManager'],
        ],
    ]));

    app(ModuleGenerator::class)($blueprint);

    $listenerPath = generatedModulesPath().'/garage-fleet/src/Hooks/NotifyFleetManager.php';
    expect(File::isFile($listenerPath))->toBeTrue();

    $listenerContents = (string) file_get_contents($listenerPath);
    expect($listenerContents)->toContain('namespace Garage\Fleet\Hooks;')
        ->and($listenerContents)->toContain('final class NotifyFleetManager')
        ->and($listenerContents)->toContain('public function __invoke(mixed ...$args): void');

    /** @var array<string, mixed> $manifest */
    $manifest = json_decode((string) file_get_contents(generatedModulesPath().'/garage-fleet/module.json'), associative: true);

    expect($manifest['hooks']['emits'])->toBe(['garage.fleet.car.serviced'])
        ->and($manifest['hooks']['listens'])->toBe(['baobab.content.saved' => 'Garage\\Fleet\\Hooks\\NotifyFleetManager']);
});

it('omits the menus block entirely from module.json when no menu item is declared', function () {
    $blueprint = ModuleBlueprint::fromJson(moduleBlueprintJson());

    app(ModuleGenerator::class)($blueprint);

    /** @var array<string, mixed> $manifest */
    $manifest = json_decode((string) file_get_contents(generatedModulesPath().'/garage-fleet/module.json'), associative: true);

    expect($manifest)->not->toHaveKey('menus');
});

it('writes declared admin menu items to module.json, unchanged (no transformation)', function () {
    $blueprint = ModuleBlueprint::fromJson(moduleBlueprintJson([
        'menus' => [
            'admin' => [
                [
                    'label' => 'Flotte',
                    'icon' => 'bi-truck',
                    'route' => 'admin.fleet.cars.index',
                    'permission' => 'fleet.cars.view',
                    'order' => 10,
                    'children' => [
                        ['label' => 'Voitures', 'route' => 'admin.fleet.cars.index'],
                    ],
                ],
            ],
        ],
    ]));

    app(ModuleGenerator::class)($blueprint);

    /** @var array<string, mixed> $manifest */
    $manifest = json_decode((string) file_get_contents(generatedModulesPath().'/garage-fleet/module.json'), associative: true);

    expect($manifest['menus']['admin'])->toBe([
        [
            'label' => 'Flotte',
            'icon' => 'bi-truck',
            'route' => 'admin.fleet.cars.index',
            'permission' => 'fleet.cars.view',
            'order' => 10,
            'children' => [
                ['label' => 'Voitures', 'route' => 'admin.fleet.cars.index'],
            ],
        ],
    ]);
});

it('regenerates silently when nothing has changed since the last generation', function () {
    $blueprint = ModuleBlueprint::fromJson(moduleBlueprintJson());

    app(ModuleGenerator::class)($blueprint);
    $moduleName = app(ModuleGenerator::class)($blueprint);

    expect($moduleName)->toBe('garage/fleet');
});

it('refuses to overwrite a generated file that was hand-edited', function () {
    $blueprint = ModuleBlueprint::fromJson(moduleBlueprintJson());

    app(ModuleGenerator::class)($blueprint);

    $providerPath = generatedModulesPath().'/garage-fleet/src/Providers/FleetServiceProvider.php';
    File::append($providerPath, "\n// édité à la main\n");

    expect(fn () => app(ModuleGenerator::class)($blueprint))
        ->toThrow(GeneratedFileConflictException::class);
});
