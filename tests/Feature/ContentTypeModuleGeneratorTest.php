<?php

use Baobab\ContentTypes\Actions\BuildContentType;
use Baobab\ContentTypes\Actions\CreateContentType;
use Baobab\ContentTypes\Exceptions\GeneratedFileConflictException;
use Baobab\ContentTypes\Generator\ContentTypeModuleGenerator;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    File::deleteDirectory(generatedModulesPath());
    config(['baobab.content_types.modules_path' => generatedModulesPath()]);
    config(['baobab.modules.paths' => ['local' => [generatedModulesPath().'/*']]]);
});

afterEach(function () {
    File::deleteDirectory(generatedModulesPath());
});

it('generates a complete module tree for a blueprint', function () {
    $contentType = app(CreateContentType::class)(carBlueprintJson());

    $moduleName = app(ContentTypeModuleGenerator::class)($contentType);

    expect($moduleName)->toBe('content-types/cars');

    $moduleDir = generatedModulesPath().'/content-cars';

    expect(File::isFile($moduleDir.'/module.json'))->toBeTrue()
        ->and(File::isFile($moduleDir.'/src/Models/Car.php'))->toBeTrue()
        ->and(File::isFile($moduleDir.'/src/Policies/CarPolicy.php'))->toBeTrue()
        ->and(File::isFile($moduleDir.'/src/Providers/CarServiceProvider.php'))->toBeTrue()
        ->and(File::isFile($moduleDir.'/.baobab-checksums.json'))->toBeTrue();

    $migrationFiles = File::glob($moduleDir.'/database/migrations/*.php');
    expect($migrationFiles)->toHaveCount(1)
        ->and(file_get_contents($migrationFiles[0]))->toContain("Schema::create('ct_cars'");

    /** @var array<string, mixed> $manifest */
    $manifest = json_decode((string) file_get_contents($moduleDir.'/module.json'), associative: true);

    expect($manifest['name'])->toBe('content-types/cars')
        ->and($manifest['type'])->toBe('content-type')
        ->and($manifest['provider'])->toBe('Modules\\Car\\Providers\\CarServiceProvider')
        ->and($manifest['autoload']['psr-4'])->toBe(['Modules\\Car\\' => 'src/'])
        ->and(collect((array) $manifest['permissions'])->pluck('key')->all())->toBe([
            'content_types.car.view',
            'content_types.car.create',
            'content_types.car.update',
            'content_types.car.delete',
        ]);
});

it('adds a unique slug column to the migration when the type is addressable', function () {
    $contentType = app(CreateContentType::class)(carBlueprintJson(['is_addressable' => true]));

    app(ContentTypeModuleGenerator::class)($contentType);

    $migrationFiles = File::glob(generatedModulesPath().'/content-cars/database/migrations/*.php');
    expect(file_get_contents($migrationFiles[0]))->toContain("\$table->string('slug')->unique();");
});

it('regenerates silently when nothing has changed since the last generation', function () {
    $contentType = app(CreateContentType::class)(carBlueprintJson());

    app(ContentTypeModuleGenerator::class)($contentType);
    $moduleName = app(ContentTypeModuleGenerator::class)($contentType);

    expect($moduleName)->toBe('content-types/cars');
});

it('refuses to overwrite a generated file that was hand-edited', function () {
    $contentType = app(CreateContentType::class)(carBlueprintJson());

    app(ContentTypeModuleGenerator::class)($contentType);

    $providerPath = generatedModulesPath().'/content-cars/src/Providers/CarServiceProvider.php';
    File::append($providerPath, "\n// édité à la main\n");

    expect(fn () => app(ContentTypeModuleGenerator::class)($contentType))
        ->toThrow(GeneratedFileConflictException::class);
});

it('generates a real column and validation-friendly fillable for a declared field', function () {
    $contentType = app(CreateContentType::class)(carBlueprintJson([
        'fields' => [['key' => 'brand', 'type' => 'text']],
    ]));

    app(ContentTypeModuleGenerator::class)($contentType);

    $moduleDir = generatedModulesPath().'/content-cars';

    $migrationFiles = File::glob($moduleDir.'/database/migrations/*.php');
    expect(file_get_contents($migrationFiles[0]))->toContain("\$table->string('brand', 255);");

    $modelContents = (string) file_get_contents($moduleDir.'/src/Models/Car.php');
    expect($modelContents)->toContain("'brand',");
});

it('includes a cast in the generated model for a field whose type declares one', function () {
    $contentType = app(CreateContentType::class)(carBlueprintJson([
        'fields' => [['key' => 'specs', 'type' => 'json']],
    ]));

    app(ContentTypeModuleGenerator::class)($contentType);

    $modelContents = (string) file_get_contents(generatedModulesPath().'/content-cars/src/Models/Car.php');
    expect($modelContents)->toContain("'specs' => 'array',");
});

it('generates a real FK column and belongsTo method for a one_to_many relation to an existing content type', function () {
    app(BuildContentType::class)((string) json_encode([
        'key' => 'Brand',
        'label' => ['singular' => 'Marque', 'plural' => 'Marques'],
    ]));

    $contentType = app(CreateContentType::class)(carBlueprintJson([
        'relations' => [['key' => 'brand', 'type' => 'one_to_many', 'target' => 'Brand']],
    ]));

    app(ContentTypeModuleGenerator::class)($contentType);

    $moduleDir = generatedModulesPath().'/content-cars';

    $migrationFiles = File::glob($moduleDir.'/database/migrations/*.php');
    $migrationContents = collect($migrationFiles)->map(fn (string $path) => (string) file_get_contents($path))->implode("\n");
    expect($migrationContents)->toContain("\$table->foreignId('brand_id')->nullable()->constrained('ct_brands')->restrictOnDelete();");

    $modelContents = (string) file_get_contents($moduleDir.'/src/Models/Car.php');
    expect($modelContents)->toContain('public function brand(): \Illuminate\Database\Eloquent\Relations\BelongsTo')
        ->and($modelContents)->toContain("'brand_id',");
});

it('generates a pivot migration and belongsToMany method for a many_to_many relation', function () {
    app(BuildContentType::class)((string) json_encode([
        'key' => 'Option',
        'label' => ['singular' => 'Option', 'plural' => 'Options'],
    ]));

    $contentType = app(CreateContentType::class)(carBlueprintJson([
        'relations' => [['key' => 'options', 'type' => 'many_to_many', 'target' => 'Option']],
    ]));

    app(ContentTypeModuleGenerator::class)($contentType);

    $moduleDir = generatedModulesPath().'/content-cars';

    $migrationFiles = File::glob($moduleDir.'/database/migrations/*.php');
    expect($migrationFiles)->toHaveCount(2);

    $pivotFile = collect($migrationFiles)->first(fn (string $path) => str_contains($path, 'ct_car_option'));
    expect($pivotFile)->not->toBeNull()
        ->and((string) file_get_contents($pivotFile))->toContain("Schema::create('ct_car_option'");

    $carsMigration = collect($migrationFiles)->first(fn (string $path) => ! str_contains($path, 'ct_car_option'));
    expect((string) file_get_contents($carsMigration))->not->toContain('options_id');

    $modelContents = (string) file_get_contents($moduleDir.'/src/Models/Car.php');
    expect($modelContents)->toContain('public function options(): \Illuminate\Database\Eloquent\Relations\BelongsToMany');
});
