<?php

use Baobab\ContentTypes\Evolution\EvolutionMigrationGenerator;
use Baobab\ContentTypes\Exceptions\UnsafeTypeChangeException;
use Baobab\ContentTypes\Models\ContentType;
use Illuminate\Support\Facades\File;

function evolutionModuleDir(): string
{
    return generatedModulesPath().'/evolution-test-module';
}

/**
 * @param  list<array<string, mixed>>  $added
 * @param  list<array<string, mixed>>  $removed
 * @param  list<array{from: string, to: array<string, mixed>}>  $renamed
 * @param  list<array{key: string, from_type: string, from: array<string, mixed>, to: array<string, mixed>}>  $typeChanged
 * @return array{
 *     added: list<array<string, mixed>>,
 *     removed: list<array<string, mixed>>,
 *     renamed: list<array{from: string, to: array<string, mixed>}>,
 *     type_changed: list<array{key: string, from_type: string, from: array<string, mixed>, to: array<string, mixed>}>,
 * }
 */
function diffWith(array $added = [], array $removed = [], array $renamed = [], array $typeChanged = []): array
{
    return ['added' => $added, 'removed' => $removed, 'renamed' => $renamed, 'type_changed' => $typeChanged];
}

beforeEach(function () {
    File::deleteDirectory(generatedModulesPath());
});

afterEach(function () {
    File::deleteDirectory(generatedModulesPath());
});

it('returns null and writes nothing for an empty diff', function () {
    $contentType = new ContentType(['key' => 'Car', 'table_name' => 'ct_cars']);

    $filename = app(EvolutionMigrationGenerator::class)->generate($contentType, diffWith(), evolutionModuleDir());

    expect($filename)->toBeNull()
        ->and(File::glob(evolutionModuleDir().'/database/migrations/*.php'))->toBe([]);
});

it('generates an ADD COLUMN up and a dropColumn down for an added field', function () {
    $contentType = new ContentType(['key' => 'Car', 'table_name' => 'ct_cars']);
    $diff = diffWith(added: [['key' => 'mileage', 'type' => 'integer']]);

    $filename = app(EvolutionMigrationGenerator::class)->generate($contentType, $diff, evolutionModuleDir());

    if ($filename === null) {
        throw new RuntimeException('Expected a migration file to be generated for an added field.');
    }

    $contents = (string) file_get_contents(evolutionModuleDir().'/'.$filename);

    expect($contents)->toContain("\$table->integer('mileage')->nullable();")
        ->and($contents)->toContain("\$table->dropColumn('mileage');");
});

it('generates a dropColumn up and a re-add down for a removed field', function () {
    $contentType = new ContentType(['key' => 'Car', 'table_name' => 'ct_cars']);
    $diff = diffWith(removed: [['key' => 'mileage', 'type' => 'integer']]);

    $filename = app(EvolutionMigrationGenerator::class)->generate($contentType, $diff, evolutionModuleDir());

    if ($filename === null) {
        throw new RuntimeException('Expected a migration file to be generated for a removed field.');
    }

    $contents = (string) file_get_contents(evolutionModuleDir().'/'.$filename);

    $lines = explode("\n", $contents);
    $upSection = implode("\n", array_slice($lines, 0, (int) array_search('    public function down(): void', $lines, true)));

    expect($upSection)->toContain("\$table->dropColumn('mileage');")
        ->and($contents)->toContain("\$table->integer('mileage')->nullable();");
});

it('generates renameColumn both ways for a renamed field', function () {
    $contentType = new ContentType(['key' => 'Car', 'table_name' => 'ct_cars']);
    $diff = diffWith(renamed: [['from' => 'brand', 'to' => ['key' => 'make', 'type' => 'text']]]);

    $filename = app(EvolutionMigrationGenerator::class)->generate($contentType, $diff, evolutionModuleDir());

    if ($filename === null) {
        throw new RuntimeException('Expected a migration file to be generated for a renamed field.');
    }

    $contents = (string) file_get_contents(evolutionModuleDir().'/'.$filename);

    expect($contents)->toContain("\$table->renameColumn('brand', 'make');")
        ->and($contents)->toContain("\$table->renameColumn('make', 'brand');");
});

it('generates a ->change() line for a safe type conversion', function () {
    $contentType = new ContentType(['key' => 'Car', 'table_name' => 'ct_cars']);
    $diff = diffWith(typeChanged: [[
        'key' => 'notes',
        'from_type' => 'text',
        'from' => ['key' => 'notes', 'type' => 'text'],
        'to' => ['key' => 'notes', 'type' => 'textarea'],
    ]]);

    $filename = app(EvolutionMigrationGenerator::class)->generate($contentType, $diff, evolutionModuleDir());

    if ($filename === null) {
        throw new RuntimeException('Expected a migration file to be generated for a type change.');
    }

    $contents = (string) file_get_contents(evolutionModuleDir().'/'.$filename);

    expect($contents)->toContain("\$table->text('notes')->nullable()->change();")
        ->and($contents)->toContain("\$table->string('notes', 255)->nullable()->change();");
});

it('returns null and writes nothing for an added field without its own column', function () {
    $contentType = new ContentType(['key' => 'Car', 'table_name' => 'ct_cars']);
    $diff = diffWith(added: [['key' => 'gallery', 'type' => 'gallery']]);

    $filename = app(EvolutionMigrationGenerator::class)->generate($contentType, $diff, evolutionModuleDir());

    expect($filename)->toBeNull()
        ->and(File::glob(evolutionModuleDir().'/database/migrations/*.php'))->toBe([]);
});

it('returns null and writes nothing for a removed field without its own column', function () {
    $contentType = new ContentType(['key' => 'Car', 'table_name' => 'ct_cars']);
    $diff = diffWith(removed: [['key' => 'gallery', 'type' => 'gallery']]);

    $filename = app(EvolutionMigrationGenerator::class)->generate($contentType, $diff, evolutionModuleDir());

    expect($filename)->toBeNull()
        ->and(File::glob(evolutionModuleDir().'/database/migrations/*.php'))->toBe([]);
});

it('skips a columnless field while still emitting the column for a sibling added field', function () {
    $contentType = new ContentType(['key' => 'Car', 'table_name' => 'ct_cars']);
    $diff = diffWith(added: [
        ['key' => 'gallery', 'type' => 'gallery'],
        ['key' => 'mileage', 'type' => 'integer'],
    ]);

    $filename = app(EvolutionMigrationGenerator::class)->generate($contentType, $diff, evolutionModuleDir());

    if ($filename === null) {
        throw new RuntimeException('Expected a migration file to be generated for the sibling added field.');
    }

    $contents = (string) file_get_contents(evolutionModuleDir().'/'.$filename);

    expect($contents)->toContain("\$table->integer('mileage')->nullable();")
        ->and($contents)->not->toContain('gallery');
});

it('derives the column nullability from the required flag, in both directions', function () {
    $contentType = new ContentType(['key' => 'Car', 'table_name' => 'ct_cars']);
    $diff = diffWith(added: [
        ['key' => 'mileage', 'type' => 'integer', 'required' => true],
        ['key' => 'notes', 'type' => 'textarea', 'required' => false],
        ['key' => 'sold_on', 'type' => 'date', 'required' => true],
    ]);

    $filename = app(EvolutionMigrationGenerator::class)->generate($contentType, $diff, evolutionModuleDir());

    if ($filename === null) {
        throw new RuntimeException('Expected a migration file to be generated.');
    }

    $contents = (string) file_get_contents(evolutionModuleDir().'/'.$filename);

    // Les deux sens du n° 137 : un type qui écrivait NOT NULL en dur devient
    // nullable quand le champ est facultatif, et un type qui écrivait
    // ->nullable() en dur cesse de l'être quand le champ est obligatoire.
    expect($contents)->toContain("\$table->integer('mileage');")
        ->and($contents)->toContain("\$table->text('notes')->nullable();")
        ->and($contents)->toContain("\$table->date('sold_on');")
        ->and($contents)->not->toContain("\$table->date('sold_on')->nullable();");
});

it('refuses a type conversion outside the safe whitelist', function () {
    $contentType = new ContentType(['key' => 'Car', 'table_name' => 'ct_cars']);
    $diff = diffWith(typeChanged: [[
        'key' => 'notes',
        'from_type' => 'text',
        'from' => ['key' => 'notes', 'type' => 'text'],
        'to' => ['key' => 'notes', 'type' => 'integer'],
    ]]);

    expect(fn () => app(EvolutionMigrationGenerator::class)->generate($contentType, $diff, evolutionModuleDir()))
        ->toThrow(UnsafeTypeChangeException::class);

    expect(File::glob(evolutionModuleDir().'/database/migrations/*.php'))->toBe([]);
});
