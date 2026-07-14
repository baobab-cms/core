<?php

use Baobab\Access\Actions\GrantPermission;
use Baobab\ContentTypes\Actions\BuildContentType;
use Baobab\ContentTypes\Actions\PublishContentEntry;
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
function draftEndpointCarType(): array
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

/**
 * @param  list<string>  $permissions
 */
function draftEndpointActor(array $permissions): User
{
    static $counter = 0;
    $counter++;

    $user = User::create([
        'name' => "Draft Endpoint Actor {$counter}",
        'email' => "draft-endpoint-actor-{$counter}@example.com",
        'password' => 'secret',
    ]);

    app(GrantPermission::class)($user, 'baobab.admin.access');

    foreach ($permissions as $permission) {
        app(GrantPermission::class)($user, $permission);
    }

    return $user;
}

it('saves a working draft instead of updating the live row when intent=draft on published content', function () {
    [$type, $modelClass] = draftEndpointCarType();
    $entry = $modelClass::create(['brand' => 'Renault'])->fresh();
    app(PublishContentEntry::class)($type, $entry);
    $actor = draftEndpointActor(['content.car.update_any']);

    $this->actingAs($actor, 'baobab')
        ->put(route('admin.content.update', ['contentType' => 'cars', 'entry' => $entry->id]), [
            'brand' => 'Renault Draft',
            'intent' => 'draft',
        ])
        ->assertRedirect(route('admin.content.edit', ['contentType' => 'cars', 'entry' => $entry->id]));

    expect($entry->fresh()->brand)->toBe('Renault')
        ->and(Revision::where('type', 'working_draft')->count())->toBe(1);
});

it('updates the live row directly when intent is the default (not draft)', function () {
    [$type, $modelClass] = draftEndpointCarType();
    $entry = $modelClass::create(['brand' => 'Renault'])->fresh();
    app(PublishContentEntry::class)($type, $entry);
    $actor = draftEndpointActor(['content.car.update_any']);

    $this->actingAs($actor, 'baobab')
        ->put(route('admin.content.update', ['contentType' => 'cars', 'entry' => $entry->id]), ['brand' => 'Renault v2'])
        ->assertRedirect(route('admin.content.index', ['contentType' => 'cars']));

    expect($entry->fresh()->brand)->toBe('Renault v2');
});

it('publishes a working draft through the endpoint', function () {
    [$type, $modelClass] = draftEndpointCarType();
    $entry = $modelClass::create(['brand' => 'Renault'])->fresh();
    app(PublishContentEntry::class)($type, $entry);
    $actor = draftEndpointActor(['content.car.update_any', 'content.car.publish_any']);

    $this->actingAs($actor, 'baobab')
        ->put(route('admin.content.update', ['contentType' => 'cars', 'entry' => $entry->id]), [
            'brand' => 'Renault Draft',
            'intent' => 'draft',
        ]);

    $this->actingAs($actor, 'baobab')
        ->post(route('admin.content.working-draft.publish', ['contentType' => 'cars', 'entry' => $entry->id]))
        ->assertRedirect();

    expect($entry->fresh()->brand)->toBe('Renault Draft')
        ->and(Revision::where('type', 'working_draft')->count())->toBe(0);
});

it('discards a working draft through the endpoint', function () {
    [$type, $modelClass] = draftEndpointCarType();
    $entry = $modelClass::create(['brand' => 'Renault'])->fresh();
    app(PublishContentEntry::class)($type, $entry);
    $actor = draftEndpointActor(['content.car.update_any']);

    $this->actingAs($actor, 'baobab')
        ->put(route('admin.content.update', ['contentType' => 'cars', 'entry' => $entry->id]), [
            'brand' => 'Renault Draft',
            'intent' => 'draft',
        ]);

    $this->actingAs($actor, 'baobab')
        ->post(route('admin.content.working-draft.discard', ['contentType' => 'cars', 'entry' => $entry->id]))
        ->assertRedirect();

    expect(Revision::where('type', 'working_draft')->count())->toBe(0)
        ->and($entry->fresh()->brand)->toBe('Renault');
});

it('accepts an autosave without validating required fields', function () {
    [$type, $modelClass] = draftEndpointCarType();
    $entry = $modelClass::create(['brand' => 'Renault']);
    $actor = draftEndpointActor(['content.car.update_any']);

    $this->actingAs($actor, 'baobab')
        ->postJson(route('admin.content.autosave', ['contentType' => 'cars', 'entry' => $entry->id]), ['brand' => ''])
        ->assertOk()
        ->assertJson(['saved' => true]);

    expect(Revision::where('type', 'autosave')->count())->toBe(1);
});
