<?php

use Baobab\ContentTypes\Actions\BuildContentType;
use Baobab\ContentTypes\Actions\RestoreContentRevision;
use Baobab\ContentTypes\Actions\SaveContentEntry;
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
function buildRestoreCar(): array
{
    $contentType = app(BuildContentType::class)(carBlueprintJson([
        'fields' => [['key' => 'brand', 'type' => 'text', 'required' => true]],
    ]));

    $module = Module::findOrFail($contentType->module_id);
    app(ModuleAutoloader::class)->registerFor($module);

    /** @var class-string<Model> $modelClass */
    $modelClass = $contentType->modelClass();

    return [$contentType->fresh(), $modelClass];
}

function restoreRevisionActor(): User
{
    static $counter = 0;
    $counter++;

    return User::create([
        'name' => "Restore Actor {$counter}",
        'email' => "restore-actor-{$counter}@example.com",
        'password' => 'secret',
    ]);
}

it('restores a past revision as a fresh save, keeping status untouched', function () {
    $type = buildRestoreCar()[0];
    $actor = restoreRevisionActor();

    $entry = app(SaveContentEntry::class)($type, ['brand' => 'v1'], $actor);
    app(SaveContentEntry::class)($type, ['brand' => 'v2'], $actor, $entry);
    app(SaveContentEntry::class)($type, ['brand' => 'v3'], $actor, $entry);

    $firstRevision = Revision::where('revisionable_type', $entry->getMorphClass())
        ->where('revisionable_id', $entry->id)
        ->where('type', 'manual')
        ->orderBy('id')
        ->first();

    expect($firstRevision->snapshot['brand'])->toBe('v1');

    app(RestoreContentRevision::class)($type, $entry, $firstRevision, $actor);

    expect($entry->fresh()->brand)->toBe('v1');
});

it('captures a pre_restore snapshot of the state just before restoring', function () {
    $type = buildRestoreCar()[0];
    $actor = restoreRevisionActor();

    $entry = app(SaveContentEntry::class)($type, ['brand' => 'v1'], $actor);
    app(SaveContentEntry::class)($type, ['brand' => 'v2'], $actor, $entry);

    $firstRevision = Revision::where('type', 'manual')->orderBy('id')->first();

    app(RestoreContentRevision::class)($type, $entry, $firstRevision, $actor);

    $preRestore = Revision::where('revisionable_type', $entry->getMorphClass())
        ->where('revisionable_id', $entry->id)
        ->where('type', 'pre_restore')
        ->sole();

    expect($preRestore->snapshot['brand'])->toBe('v2');
});

it('does not remove restore history — it creates a new manual revision on top', function () {
    $type = buildRestoreCar()[0];
    $actor = restoreRevisionActor();

    $entry = app(SaveContentEntry::class)($type, ['brand' => 'v1'], $actor);
    app(SaveContentEntry::class)($type, ['brand' => 'v2'], $actor, $entry);

    $firstRevision = Revision::where('type', 'manual')->orderBy('id')->first();
    app(RestoreContentRevision::class)($type, $entry, $firstRevision, $actor);

    // v1, v2, + la révision manuelle créée par la restauration elle-même (SaveContentEntry-like).
    expect(Revision::where('type', 'manual')->count())->toBe(3)
        ->and(Revision::where('type', 'manual')->latest('id')->first()->snapshot['brand'])->toBe('v1');
});
