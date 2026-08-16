<?php

use Baobab\ContentTypes\Actions\AutosaveContentEntry;
use Baobab\ContentTypes\Actions\BuildContentType;
use Baobab\ContentTypes\Editorial\Models\Revision;
use Baobab\ContentTypes\Models\ContentType;
use Baobab\Modules\Models\Module;
use Baobab\Modules\ModuleAutoloader;
use Baobab\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    File::deleteDirectory(generatedModulesPath());
    config(['baobab.content_types.modules_path' => generatedModulesPath()]);
    config(['baobab.modules.paths' => ['local' => [generatedModulesPath().'/*']]]);
});

afterEach(function () {
    File::deleteDirectory(generatedModulesPath());
});

/**
 * @return array{0: ContentType, 1: class-string<Model>}
 */
function buildAutosaveCar(): array
{
    $contentType = app(BuildContentType::class)(contentTypeBlueprintJson('AutosaveEntry', [
        'fields' => [['key' => 'brand', 'type' => 'text', 'required' => true]],
    ]));

    $module = Module::findOrFail($contentType->module_id);
    app(ModuleAutoloader::class)->registerFor($module);

    /** @var class-string<Model> $modelClass */
    $modelClass = $contentType->modelClass();

    return [$contentType->fresh(), $modelClass];
}

function autosaveActor(): User
{
    static $counter = 0;
    $counter++;

    return User::create([
        'name' => "Autosave Actor {$counter}",
        'email' => "autosave-actor-{$counter}@example.com",
        'password' => 'secret',
    ]);
}

it('creates an autosave revision without touching the live row or the audit log', function () {
    [$type, $modelClass] = buildAutosaveCar();
    $entry = $modelClass::create(['brand' => 'Renault'])->fresh();
    $actor = autosaveActor();

    app(AutosaveContentEntry::class)($type, $entry, ['brand' => 'Renault en cours'], $actor);

    expect($entry->fresh()->brand)->toBe('Renault');

    $autosave = Revision::where('revisionable_type', $entry->getMorphClass())
        ->where('revisionable_id', $entry->id)
        ->where('type', 'autosave')
        ->sole();

    expect($autosave->snapshot['brand'])->toBe('Renault en cours');
});

it('overwrites the same autosave row per user, no history piling up', function () {
    [$type, $modelClass] = buildAutosaveCar();
    $entry = $modelClass::create(['brand' => 'Renault'])->fresh();
    $actor = autosaveActor();

    app(AutosaveContentEntry::class)($type, $entry, ['brand' => 'v1'], $actor);
    app(AutosaveContentEntry::class)($type, $entry, ['brand' => 'v2'], $actor);

    expect(Revision::where('type', 'autosave')->count())->toBe(1)
        ->and(Revision::where('type', 'autosave')->sole()->snapshot['brand'])->toBe('v2');
});

it('keeps a distinct autosave per user editing the same content', function () {
    [$type, $modelClass] = buildAutosaveCar();
    $entry = $modelClass::create(['brand' => 'Renault'])->fresh();
    $alice = autosaveActor();
    $bob = autosaveActor();

    app(AutosaveContentEntry::class)($type, $entry, ['brand' => 'Alice version'], $alice);
    app(AutosaveContentEntry::class)($type, $entry, ['brand' => 'Bob version'], $bob);

    expect(Revision::where('type', 'autosave')->count())->toBe(2);
});
