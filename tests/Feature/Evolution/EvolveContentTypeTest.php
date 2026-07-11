<?php

use Baobab\Audit\Models\AuditEntry;
use Baobab\ContentTypes\Actions\BuildContentType;
use Baobab\ContentTypes\Actions\EvolveContentType;
use Baobab\ContentTypes\Exceptions\ContentTypeNotBuiltException;
use Baobab\ContentTypes\Exceptions\DestructiveChangeNotConfirmedException;
use Baobab\ContentTypes\Exceptions\InvalidBlueprintException;
use Baobab\ContentTypes\Exceptions\UnsafeTypeChangeException;
use Baobab\ContentTypes\Models\ContentType;
use Baobab\Facades\Hook;
use Baobab\Modules\Models\Module;
use Baobab\Modules\ModuleAutoloader;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    File::deleteDirectory(generatedModulesPath());
    config(['baobab.content_types.modules_path' => generatedModulesPath()]);
    config(['baobab.modules.paths' => ['local' => [generatedModulesPath().'/*']]]);
});

afterEach(function () {
    File::deleteDirectory(generatedModulesPath());
});

/**
 * @param  list<array<string, mixed>>  $fields
 */
function blueprintFor(string $key, array $fields = []): string
{
    return (string) json_encode([
        'key' => $key,
        'label' => ['singular' => $key, 'plural' => $key],
        'fields' => $fields,
    ]);
}

it('adds a real column and regenerates the model, bumping the version and journaling the diff', function () {
    $contentType = app(BuildContentType::class)(blueprintFor('Bike'));

    $received = null;
    Hook::listen('baobab.content_type.evolved', function ($ct, $diff) use (&$received): void {
        $received = $diff;
    });

    $evolved = app(EvolveContentType::class)($contentType, blueprintFor('Bike', [
        ['key' => 'gears', 'type' => 'integer'],
    ]));

    expect($evolved->version)->toBe(2)
        ->and(Schema::hasColumn('ct_bikes', 'gears'))->toBeTrue();

    $modelContents = (string) file_get_contents($evolved->moduleDir().'/src/Models/Bike.php');
    expect($modelContents)->toContain("'gears',");

    expect($received)->not->toBeNull()
        ->and($received['added'])->toBe([['key' => 'gears', 'type' => 'integer']]);

    expect(AuditEntry::where('action', 'content_type.evolved')->where('auditable_id', $contentType->id)->exists())->toBeTrue();
});

it('refuses to remove a field without explicit confirmation, changing nothing', function () {
    $contentType = app(BuildContentType::class)(blueprintFor('Scooter', [
        ['key' => 'color', 'type' => 'text'],
    ]));

    expect(fn () => app(EvolveContentType::class)($contentType, blueprintFor('Scooter')))
        ->toThrow(DestructiveChangeNotConfirmedException::class);

    expect(Schema::hasColumn('ct_scooters', 'color'))->toBeTrue()
        ->and(ContentType::findOrFail($contentType->id)->version)->toBe(1);
});

it('drops the column when the removal is explicitly confirmed', function () {
    $contentType = app(BuildContentType::class)(blueprintFor('Moped', [
        ['key' => 'color', 'type' => 'text'],
    ]));

    app(EvolveContentType::class)($contentType, blueprintFor('Moped'), confirmDestructive: true);

    expect(Schema::hasColumn('ct_mopeds', 'color'))->toBeFalse();
});

it('renames a column while preserving existing data', function () {
    $contentType = app(BuildContentType::class)(blueprintFor('Van', [
        ['key' => 'brand', 'type' => 'text'],
    ]));

    $module = Module::findOrFail($contentType->module_id);
    app(ModuleAutoloader::class)->registerFor($module);
    $vanClass = $contentType->modelClass();
    /** @var Model $van */
    $van = new $vanClass(['brand' => 'Renault']);
    $van->save();

    app(EvolveContentType::class)($contentType, blueprintFor('Van', [
        ['key' => 'make', 'type' => 'text', 'renamed_from' => 'brand'],
    ]));

    expect(Schema::hasColumn('ct_vans', 'make'))->toBeTrue()
        ->and(Schema::hasColumn('ct_vans', 'brand'))->toBeFalse();

    $van = $van->fresh();
    if ($van === null) {
        throw new RuntimeException('Expected the Van row to still exist after the rename.');
    }
    expect($van->getAttribute('make'))->toBe('Renault');
});

it('applies a safe type change', function () {
    $contentType = app(BuildContentType::class)(blueprintFor('Trailer', [
        ['key' => 'notes', 'type' => 'text'],
    ]));

    app(EvolveContentType::class)($contentType, blueprintFor('Trailer', [
        ['key' => 'notes', 'type' => 'textarea'],
    ]));

    expect(Schema::hasColumn('ct_trailers', 'notes'))->toBeTrue();
});

it('rejects an unsafe type change, changing nothing', function () {
    $contentType = app(BuildContentType::class)(blueprintFor('Caravan', [
        ['key' => 'notes', 'type' => 'text'],
    ]));

    expect(fn () => app(EvolveContentType::class)($contentType, blueprintFor('Caravan', [
        ['key' => 'notes', 'type' => 'integer'],
    ])))->toThrow(UnsafeTypeChangeException::class);

    expect(ContentType::findOrFail($contentType->id)->version)->toBe(1);
});

it('refuses to evolve a content type that has not been built yet', function () {
    $contentType = new ContentType([
        'key' => 'Draft',
        'table_name' => 'ct_drafts',
        'is_addressable' => false,
        'version' => 1,
        'blueprint' => ['key' => 'Draft', 'label' => ['singular' => 'Draft', 'plural' => 'Drafts'], 'fields' => []],
    ]);
    $contentType->save();

    expect(fn () => app(EvolveContentType::class)($contentType, blueprintFor('Draft')))
        ->toThrow(ContentTypeNotBuiltException::class);
});

it('refuses to change the content type key during evolution', function () {
    $contentType = app(BuildContentType::class)(blueprintFor('Tricycle'));

    expect(fn () => app(EvolveContentType::class)($contentType, blueprintFor('Quad')))
        ->toThrow(InvalidBlueprintException::class);
});
