<?php

use Baobab\Access\Actions\GrantPermission;
use Baobab\ContentTypes\Actions\BuildContentType;
use Baobab\ContentTypes\Actions\DeleteContentEntry;
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
function trashEndpointCarType(): array
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
function trashEndpointActor(array $permissions): User
{
    static $counter = 0;
    $counter++;

    $user = User::create([
        'name' => "Trash Endpoint Actor {$counter}",
        'email' => "trash-endpoint-actor-{$counter}@example.com",
        'password' => 'secret',
    ]);

    app(GrantPermission::class)($user, 'baobab.admin.access');

    foreach ($permissions as $permission) {
        app(GrantPermission::class)($user, $permission);
    }

    return $user;
}

it('lists trashed entries via the trashed filter', function () {
    [$type, $modelClass] = trashEndpointCarType();
    $entry = $modelClass::create(['brand' => 'Renault']);
    app(DeleteContentEntry::class)($type, $entry);
    $actor = trashEndpointActor(['content.car.view', 'content.car.delete_any']);

    $this->actingAs($actor, 'baobab')
        ->get(route('admin.content.index', ['contentType' => 'cars', 'trashed' => 1]))
        ->assertOk()
        ->assertSee('Renault', false);
});

it('restores a trashed entry through the endpoint', function () {
    [$type, $modelClass] = trashEndpointCarType();
    $entry = $modelClass::create(['brand' => 'Renault']);
    app(DeleteContentEntry::class)($type, $entry);
    $actor = trashEndpointActor(['content.car.delete_any']);

    $this->actingAs($actor, 'baobab')
        ->post(route('admin.content.restore', ['contentType' => 'cars', 'entry' => $entry->id]))
        ->assertRedirect();

    expect($modelClass::find($entry->id))->not->toBeNull();
});

it('denies purge without baobab.trash.purge, even for the type owner', function () {
    [$type, $modelClass] = trashEndpointCarType();
    $entry = $modelClass::create(['brand' => 'Renault']);
    app(DeleteContentEntry::class)($type, $entry);
    $actor = trashEndpointActor(['content.car.delete_any']);

    $this->actingAs($actor, 'baobab')
        ->delete(route('admin.content.force-destroy', ['contentType' => 'cars', 'entry' => $entry->id]))
        ->assertForbidden();
});

it('purges a trashed entry with baobab.trash.purge', function () {
    [$type, $modelClass] = trashEndpointCarType();
    $entry = $modelClass::create(['brand' => 'Renault']);
    app(DeleteContentEntry::class)($type, $entry);
    $actor = trashEndpointActor(['content.car.delete_any', 'baobab.trash.purge']);

    $this->actingAs($actor, 'baobab')
        ->delete(route('admin.content.force-destroy', ['contentType' => 'cars', 'entry' => $entry->id]))
        ->assertRedirect();

    expect($modelClass::withTrashed()->find($entry->id))->toBeNull();
});

it('bulk restores selected trashed entries', function () {
    [$type, $modelClass] = trashEndpointCarType();
    $a = $modelClass::create(['brand' => 'A']);
    $b = $modelClass::create(['brand' => 'B']);
    app(DeleteContentEntry::class)($type, $a);
    app(DeleteContentEntry::class)($type, $b);
    $actor = trashEndpointActor(['content.car.delete_any']);

    $this->actingAs($actor, 'baobab')
        ->post(route('admin.content.bulk-restore', ['contentType' => 'cars']), ['ids' => [$a->id, $b->id]])
        ->assertRedirect();

    expect($modelClass::whereIn('id', [$a->id, $b->id])->count())->toBe(2);
});
