<?php

use Baobab\Audit\Models\AuditEntry;
use Baobab\ContentTypes\Actions\BuildContentType;
use Baobab\ContentTypes\Actions\DiscardWorkingDraftEntry;
use Baobab\ContentTypes\Actions\PublishContentEntry;
use Baobab\ContentTypes\Actions\PublishWorkingDraftEntry;
use Baobab\ContentTypes\Actions\SaveWorkingDraftEntry;
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
function buildDraftCar(): array
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

function draftActor(): User
{
    static $counter = 0;
    $counter++;

    return User::create([
        'name' => "Draft Actor {$counter}",
        'email' => "draft-actor-{$counter}@example.com",
        'password' => 'secret',
    ]);
}

it('saves a working draft without touching the live row', function () {
    [$type, $modelClass] = buildDraftCar();
    $entry = $modelClass::create(['brand' => 'Renault'])->fresh();
    app(PublishContentEntry::class)($type, $entry);
    $actor = draftActor();

    app(SaveWorkingDraftEntry::class)($type, $entry, ['brand' => 'Renault Draft'], $actor);

    expect($entry->fresh()->brand)->toBe('Renault');

    $draft = Revision::where('revisionable_type', $entry->getMorphClass())
        ->where('revisionable_id', $entry->id)
        ->where('type', 'working_draft')
        ->sole();

    expect($draft->snapshot['brand'])->toBe('Renault Draft')
        ->and($draft->author_id)->toBe($actor->id);
});

it('overwrites the single working draft on a second save, no history piling up', function () {
    [$type, $modelClass] = buildDraftCar();
    $entry = $modelClass::create(['brand' => 'Renault'])->fresh();
    app(PublishContentEntry::class)($type, $entry);
    $actor = draftActor();

    app(SaveWorkingDraftEntry::class)($type, $entry, ['brand' => 'Draft 1'], $actor);
    app(SaveWorkingDraftEntry::class)($type, $entry, ['brand' => 'Draft 2'], $actor);

    expect(Revision::where('type', 'working_draft')->count())->toBe(1)
        ->and(Revision::where('type', 'working_draft')->sole()->snapshot['brand'])->toBe('Draft 2');
});

it('applies the working draft to the live row on publish, without a status transition', function () {
    [$type, $modelClass] = buildDraftCar();
    $entry = $modelClass::create(['brand' => 'Renault'])->fresh();
    app(PublishContentEntry::class)($type, $entry);
    $actor = draftActor();

    app(SaveWorkingDraftEntry::class)($type, $entry, ['brand' => 'Renault Draft'], $actor);
    $draft = Revision::where('type', 'working_draft')->sole();

    app(PublishWorkingDraftEntry::class)($type, $entry, $draft, $actor);

    $fresh = $entry->fresh();
    expect($fresh->brand)->toBe('Renault Draft')
        ->and($fresh->status)->toBe('published')
        ->and(Revision::where('type', 'working_draft')->count())->toBe(0);

    expect(AuditEntry::where('action', 'content.working_draft.published')->exists())->toBeTrue();
});

it('never overwrites status/published_at/author_id when publishing a draft', function () {
    [$type, $modelClass] = buildDraftCar();
    $owner = draftActor();
    $entry = new $modelClass(['brand' => 'Renault']);
    $entry->author_id = $owner->id;
    $entry->save();
    $entry = $entry->fresh();
    app(PublishContentEntry::class)($type, $entry);
    $originalPublishedAt = $entry->fresh()->published_at;

    $editor = draftActor();
    app(SaveWorkingDraftEntry::class)($type, $entry, ['brand' => 'Renault Draft', 'status' => 'draft', 'author_id' => $editor->id], $editor);
    $draft = Revision::where('type', 'working_draft')->sole();

    app(PublishWorkingDraftEntry::class)($type, $entry, $draft, $editor);

    $fresh = $entry->fresh();
    expect($fresh->status)->toBe('published')
        ->and($fresh->author_id)->toBe($owner->id)
        ->and($fresh->published_at->equalTo($originalPublishedAt))->toBeTrue();
});

it('discards a working draft, leaving the live row untouched', function () {
    [$type, $modelClass] = buildDraftCar();
    $entry = $modelClass::create(['brand' => 'Renault'])->fresh();
    app(PublishContentEntry::class)($type, $entry);

    app(SaveWorkingDraftEntry::class)($type, $entry, ['brand' => 'Renault Draft'], draftActor());
    $draft = Revision::where('type', 'working_draft')->sole();

    app(DiscardWorkingDraftEntry::class)($type, $draft);

    expect(Revision::where('type', 'working_draft')->count())->toBe(0)
        ->and($entry->fresh()->brand)->toBe('Renault');
});
