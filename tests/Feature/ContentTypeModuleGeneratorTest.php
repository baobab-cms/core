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
    $contentType = app(CreateContentType::class)(contentTypeBlueprintJson('ContentTypeModuleGeneratorEntry'));

    $moduleName = app(ContentTypeModuleGenerator::class)($contentType);

    expect($moduleName)->toBe('content-types/content-type-module-generator-entries');

    $moduleDir = generatedModulesPath().'/content-content-type-module-generator-entries';

    expect(File::isFile($moduleDir.'/module.json'))->toBeTrue()
        ->and(File::isFile($moduleDir.'/src/Models/ContentTypeModuleGeneratorEntry.php'))->toBeTrue()
        ->and(File::isFile($moduleDir.'/src/Policies/ContentTypeModuleGeneratorEntryPolicy.php'))->toBeTrue()
        ->and(File::isFile($moduleDir.'/src/Providers/ContentTypeModuleGeneratorEntryServiceProvider.php'))->toBeTrue()
        ->and(File::isFile($moduleDir.'/.baobab-checksums.json'))->toBeTrue();

    $migrationFiles = File::glob($moduleDir.'/database/migrations/*.php');
    expect($migrationFiles)->toHaveCount(1)
        ->and(file_get_contents($migrationFiles[0]))->toContain("Schema::create('ct_content_type_module_generator_entries'");

    /** @var array<string, mixed> $manifest */
    $manifest = json_decode((string) file_get_contents($moduleDir.'/module.json'), associative: true);

    expect($manifest['name'])->toBe('content-types/content-type-module-generator-entries')
        ->and($manifest['type'])->toBe('content-type')
        ->and($manifest['provider'])->toBe('Modules\\ContentTypeModuleGeneratorEntry\\Providers\\ContentTypeModuleGeneratorEntryServiceProvider')
        ->and($manifest['autoload']['psr-4'])->toBe(['Modules\\ContentTypeModuleGeneratorEntry\\' => 'src/'])
        ->and(collect((array) $manifest['permissions'])->pluck('key')->all())->toBe([
            'content.content_type_module_generator_entry.view',
            'content.content_type_module_generator_entry.create',
            'content.content_type_module_generator_entry.update',
            'content.content_type_module_generator_entry.update_any',
            'content.content_type_module_generator_entry.delete',
            'content.content_type_module_generator_entry.delete_any',
            'content.content_type_module_generator_entry.publish',
            'content.content_type_module_generator_entry.publish_any',
        ])
        ->and($manifest['menus']['admin'][0])->toBe([
            'label' => 'ContentTypeModuleGeneratorEntrys',
            'route' => 'admin.content.index',
            'route_params' => ['contentType' => 'content-type-module-generator-entries'],
            'permission' => 'content.content_type_module_generator_entry.view',
        ]);
});

it('adds a unique slug column to the migration when the type is addressable', function () {
    $contentType = app(CreateContentType::class)(contentTypeBlueprintJson('ContentTypeModuleGeneratorEntry', [
        'is_addressable' => true,
        'title_field' => 'brand',
        'fields' => [['key' => 'brand', 'type' => 'text']],
    ]));

    app(ContentTypeModuleGenerator::class)($contentType);

    $migrationFiles = File::glob(generatedModulesPath().'/content-content-type-module-generator-entries/database/migrations/*.php');
    expect(file_get_contents($migrationFiles[0]))->toContain("\$table->string('slug')->unique();");
});

it('adds the unpublish_at column and cast by default', function () {
    $contentType = app(CreateContentType::class)(contentTypeBlueprintJson('ContentTypeModuleGeneratorEntry'));

    app(ContentTypeModuleGenerator::class)($contentType);

    $migrationFiles = File::glob(generatedModulesPath().'/content-content-type-module-generator-entries/database/migrations/*.php');
    $modelContents = (string) file_get_contents(generatedModulesPath().'/content-content-type-module-generator-entries/src/Models/ContentTypeModuleGeneratorEntry.php');

    expect(file_get_contents($migrationFiles[0]))->toContain("\$table->timestamp('unpublish_at')->nullable();")
        ->and($modelContents)->toContain("'unpublish_at' => 'datetime'")
        ->and($modelContents)->toContain("'unpublish_at',");
});

it('omits the unpublish_at column when disabled in the blueprint', function () {
    $contentType = app(CreateContentType::class)(contentTypeBlueprintJson('ContentTypeModuleGeneratorEntry', ['unpublish_at' => false]));

    app(ContentTypeModuleGenerator::class)($contentType);

    $migrationFiles = File::glob(generatedModulesPath().'/content-content-type-module-generator-entries/database/migrations/*.php');
    $modelContents = (string) file_get_contents(generatedModulesPath().'/content-content-type-module-generator-entries/src/Models/ContentTypeModuleGeneratorEntry.php');

    expect(file_get_contents($migrationFiles[0]))->not->toContain('unpublish_at')
        ->and($modelContents)->not->toContain('unpublish_at');
});

it('regenerates silently when nothing has changed since the last generation', function () {
    $contentType = app(CreateContentType::class)(contentTypeBlueprintJson('ContentTypeModuleGeneratorEntry'));

    app(ContentTypeModuleGenerator::class)($contentType);
    $moduleName = app(ContentTypeModuleGenerator::class)($contentType);

    expect($moduleName)->toBe('content-types/content-type-module-generator-entries');
});

it('refuses to overwrite a generated file that was hand-edited', function () {
    $contentType = app(CreateContentType::class)(contentTypeBlueprintJson('ContentTypeModuleGeneratorEntry'));

    app(ContentTypeModuleGenerator::class)($contentType);

    $providerPath = generatedModulesPath().'/content-content-type-module-generator-entries/src/Providers/ContentTypeModuleGeneratorEntryServiceProvider.php';
    File::append($providerPath, "\n// édité à la main\n");

    expect(fn () => app(ContentTypeModuleGenerator::class)($contentType))
        ->toThrow(GeneratedFileConflictException::class);
});

it('generates a real column and validation-friendly fillable for a declared field', function () {
    $contentType = app(CreateContentType::class)(contentTypeBlueprintJson('ContentTypeModuleGeneratorEntry', [
        'fields' => [['key' => 'brand', 'type' => 'text']],
    ]));

    app(ContentTypeModuleGenerator::class)($contentType);

    $moduleDir = generatedModulesPath().'/content-content-type-module-generator-entries';

    $migrationFiles = File::glob($moduleDir.'/database/migrations/*.php');
    expect(file_get_contents($migrationFiles[0]))->toContain("\$table->string('brand', 255)->nullable();");

    $modelContents = (string) file_get_contents($moduleDir.'/src/Models/ContentTypeModuleGeneratorEntry.php');
    expect($modelContents)->toContain("'brand',");
});

it('derives the column nullability from the required flag of each field', function () {
    $contentType = app(CreateContentType::class)(contentTypeBlueprintJson('ContentTypeModuleGeneratorEntry', [
        'title_field' => 'brand',
        'fields' => [
            ['key' => 'brand', 'type' => 'text', 'required' => true],
            ['key' => 'notes', 'type' => 'textarea', 'required' => false],
            ['key' => 'sold_on', 'type' => 'date', 'required' => true],
        ],
    ]));

    app(ContentTypeModuleGenerator::class)($contentType);

    $migrationFiles = File::glob(generatedModulesPath().'/content-content-type-module-generator-entries/database/migrations/*.php');
    $contents = (string) file_get_contents($migrationFiles[0]);

    // Le catalogue se trompait dans les deux sens (suivi n° 137) : `text` était
    // NOT NULL même facultatif, `date` était nullable même obligatoire.
    expect($contents)->toContain("\$table->string('brand', 255);")
        ->and($contents)->toContain("\$table->text('notes')->nullable();")
        ->and($contents)->toContain("\$table->date('sold_on');");
});

it('includes a cast in the generated model for a field whose type declares one', function () {
    $contentType = app(CreateContentType::class)(contentTypeBlueprintJson('ContentTypeModuleGeneratorEntry', [
        'fields' => [['key' => 'specs', 'type' => 'json']],
    ]));

    app(ContentTypeModuleGenerator::class)($contentType);

    $modelContents = (string) file_get_contents(generatedModulesPath().'/content-content-type-module-generator-entries/src/Models/ContentTypeModuleGeneratorEntry.php');
    expect($modelContents)->toContain("'specs' => 'array',");
});

it('excludes a gallery field from the generated migration and model fillable (no own column)', function () {
    $contentType = app(CreateContentType::class)(contentTypeBlueprintJson('ContentTypeModuleGeneratorEntry', [
        'fields' => [
            ['key' => 'brand', 'type' => 'text'],
            ['key' => 'photos', 'type' => 'gallery'],
        ],
    ]));

    app(ContentTypeModuleGenerator::class)($contentType);

    $moduleDir = generatedModulesPath().'/content-content-type-module-generator-entries';

    $migrationFiles = File::glob($moduleDir.'/database/migrations/*.php');
    expect(file_get_contents($migrationFiles[0]))->not->toContain('photos');

    $modelContents = (string) file_get_contents($moduleDir.'/src/Models/ContentTypeModuleGeneratorEntry.php');
    expect($modelContents)->toContain("'brand',")
        ->and($modelContents)->not->toContain("'photos',");
});

it('generates a real FK column and belongsTo method for a one_to_many relation to an existing content type', function () {
    app(BuildContentType::class)((string) json_encode([
        'key' => 'Brand',
        'label' => ['singular' => 'Marque', 'plural' => 'Marques'],
    ]));

    $contentType = app(CreateContentType::class)(contentTypeBlueprintJson('ContentTypeModuleGeneratorEntry', [
        'relations' => [['key' => 'brand', 'type' => 'one_to_many', 'target' => 'Brand']],
    ]));

    app(ContentTypeModuleGenerator::class)($contentType);

    $moduleDir = generatedModulesPath().'/content-content-type-module-generator-entries';

    $migrationFiles = File::glob($moduleDir.'/database/migrations/*.php');
    $migrationContents = collect($migrationFiles)->map(fn (string $path) => (string) file_get_contents($path))->implode("\n");
    expect($migrationContents)->toContain("\$table->foreignId('brand_id')->nullable()->constrained('ct_brands')->restrictOnDelete();");

    $modelContents = (string) file_get_contents($moduleDir.'/src/Models/ContentTypeModuleGeneratorEntry.php');
    expect($modelContents)->toContain('public function brand(): \Illuminate\Database\Eloquent\Relations\BelongsTo')
        ->and($modelContents)->toContain("'brand_id',");
});

it('generates a pivot migration and belongsToMany method for a many_to_many relation', function () {
    app(BuildContentType::class)((string) json_encode([
        'key' => 'Option',
        'label' => ['singular' => 'Option', 'plural' => 'Options'],
    ]));

    $contentType = app(CreateContentType::class)(contentTypeBlueprintJson('ContentTypeModuleGeneratorEntry', [
        'relations' => [['key' => 'options', 'type' => 'many_to_many', 'target' => 'Option']],
    ]));

    app(ContentTypeModuleGenerator::class)($contentType);

    $moduleDir = generatedModulesPath().'/content-content-type-module-generator-entries';

    $migrationFiles = File::glob($moduleDir.'/database/migrations/*.php');
    expect($migrationFiles)->toHaveCount(2);

    $pivotFile = collect($migrationFiles)->first(fn (string $path) => str_contains($path, 'ct_content_type_module_generator_entry_option'));
    expect($pivotFile)->not->toBeNull()
        ->and((string) file_get_contents($pivotFile))->toContain("Schema::create('ct_content_type_module_generator_entry_option'");

    $carsMigration = collect($migrationFiles)->first(fn (string $path) => ! str_contains($path, 'ct_content_type_module_generator_entry_option'));
    expect((string) file_get_contents($carsMigration))->not->toContain('options_id');

    $modelContents = (string) file_get_contents($moduleDir.'/src/Models/ContentTypeModuleGeneratorEntry.php');
    expect($modelContents)->toContain('public function options(): \Illuminate\Database\Eloquent\Relations\BelongsToMany');
});
