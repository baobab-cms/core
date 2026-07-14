<?php

use Baobab\Access\Actions\GrantPermission;
use Baobab\ContentTypes\Actions\BuildContentType;
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
function revisionsEndpointCarType(): array
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
function revisionsEndpointActor(array $permissions): User
{
    static $counter = 0;
    $counter++;

    $user = User::create([
        'name' => "Revisions Endpoint Actor {$counter}",
        'email' => "revisions-endpoint-actor-{$counter}@example.com",
        'password' => 'secret',
    ]);

    app(GrantPermission::class)($user, 'baobab.admin.access');

    foreach ($permissions as $permission) {
        app(GrantPermission::class)($user, $permission);
    }

    return $user;
}

it('lists the revision history and computes a diff when from/to are given', function () {
    [$type] = revisionsEndpointCarType();
    $actor = revisionsEndpointActor(['content.car.update']);

    $entry = app(SaveContentEntry::class)($type, ['brand' => 'v1'], $actor);
    app(SaveContentEntry::class)($type, ['brand' => 'v2'], $actor, $entry);

    $revisions = Revision::where('revisionable_id', $entry->id)->orderBy('id')->get();

    $response = $this->actingAs($actor, 'baobab')
        ->get(route('admin.content.revisions', [
            'contentType' => 'cars',
            'entry' => $entry->id,
            'from' => $revisions[0]->id,
            'to' => $revisions[1]->id,
        ]))
        ->assertOk();

    $response->assertSee('v1', false)->assertSee('v2', false);
});

it('restores a revision through the endpoint', function () {
    [$type] = revisionsEndpointCarType();
    $actor = revisionsEndpointActor(['content.car.update']);

    $entry = app(SaveContentEntry::class)($type, ['brand' => 'v1'], $actor);
    app(SaveContentEntry::class)($type, ['brand' => 'v2'], $actor, $entry);
    $first = Revision::where('revisionable_id', $entry->id)->orderBy('id')->first();

    $this->actingAs($actor, 'baobab')
        ->post(route('admin.content.revisions.restore', ['contentType' => 'cars', 'entry' => $entry->id, 'revision' => $first->id]))
        ->assertRedirect();

    expect($entry->fresh()->brand)->toBe('v1');
});

it('denies the revisions screen without the update permission', function () {
    [$type] = revisionsEndpointCarType();
    $entry = ($type->modelClass())::create(['brand' => 'v1']);
    $actor = revisionsEndpointActor([]);

    $this->actingAs($actor, 'baobab')
        ->get(route('admin.content.revisions', ['contentType' => 'cars', 'entry' => $entry->id]))
        ->assertForbidden();
});
