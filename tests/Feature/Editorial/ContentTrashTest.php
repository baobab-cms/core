<?php

use Baobab\ContentTypes\Actions\BuildContentType;
use Baobab\ContentTypes\Actions\DeleteContentEntry;
use Baobab\ContentTypes\Actions\PublishContentEntry;
use Baobab\ContentTypes\Actions\PurgeContentEntry;
use Baobab\ContentTypes\Actions\RestoreContentEntryFromTrash;
use Baobab\ContentTypes\Actions\SaveContentEntry;
use Baobab\ContentTypes\Editorial\Models\ContentLock;
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
function buildTrashCar(): array
{
    $contentType = app(BuildContentType::class)(contentTypeBlueprintJson('ContentTrashEntry', [
        'fields' => [['key' => 'brand', 'type' => 'text', 'required' => true]],
    ]));

    $module = Module::findOrFail($contentType->module_id);
    app(ModuleAutoloader::class)->registerFor($module);

    /** @var class-string<Model> $modelClass */
    $modelClass = $contentType->modelClass();

    return [$contentType->fresh(), $modelClass];
}

function trashActor(): User
{
    static $counter = 0;
    $counter++;

    return User::create([
        'name' => "Trash Actor {$counter}",
        'email' => "trash-actor-{$counter}@example.com",
        'password' => 'secret',
    ]);
}

it('restores a published entry back to draft (never straight back to published)', function () {
    [$type, $modelClass] = buildTrashCar();
    $entry = $modelClass::create(['brand' => 'Renault'])->fresh();
    app(PublishContentEntry::class)($type, $entry);

    app(DeleteContentEntry::class)($type, $entry);
    expect($modelClass::find($entry->id))->toBeNull();

    $restored = app(RestoreContentEntryFromTrash::class)($type, $entry);

    expect($restored->status)->toBe('draft')
        ->and($modelClass::find($entry->id))->not->toBeNull();
});

it('restores a draft entry as draft, unchanged', function () {
    [$type, $modelClass] = buildTrashCar();
    $entry = $modelClass::create(['brand' => 'Renault'])->fresh();

    app(DeleteContentEntry::class)($type, $entry);
    $restored = app(RestoreContentEntryFromTrash::class)($type, $entry);

    expect($restored->status)->toBe('draft');
});

it('purges a trashed entry along with its revisions and lock', function () {
    [$type, $modelClass] = buildTrashCar();
    $actor = trashActor();

    $entry = app(SaveContentEntry::class)($type, ['brand' => 'Renault'], $actor);
    ContentLock::create(['lockable_type' => $entry->getMorphClass(), 'lockable_id' => $entry->id, 'user_id' => $actor->id]);

    expect(Revision::where('revisionable_id', $entry->id)->count())->toBeGreaterThan(0)
        ->and(ContentLock::count())->toBe(1);

    app(DeleteContentEntry::class)($type, $entry);
    app(PurgeContentEntry::class)($type, $entry);

    expect($modelClass::withTrashed()->find($entry->id))->toBeNull()
        ->and(Revision::where('revisionable_id', $entry->id)->count())->toBe(0)
        ->and(ContentLock::count())->toBe(0);
});
