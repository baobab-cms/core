<?php

use Baobab\Access\Actions\GrantPermission;
use Baobab\ContentTypes\Actions\AcquireOrRefreshContentLock;
use Baobab\ContentTypes\Actions\BuildContentType;
use Baobab\ContentTypes\Editorial\Models\ContentLock;
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
function lockEndpointCarType(): array
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
function lockEndpointActor(array $permissions): User
{
    static $counter = 0;
    $counter++;

    $user = User::create([
        'name' => "Lock Endpoint Actor {$counter}",
        'email' => "lock-endpoint-actor-{$counter}@example.com",
        'password' => 'secret',
    ]);

    app(GrantPermission::class)($user, 'baobab.admin.access');

    foreach ($permissions as $permission) {
        app(GrantPermission::class)($user, $permission);
    }

    return $user;
}

it('shows the edit form in read-only mode when another user holds the lock', function () {
    [, $modelClass] = lockEndpointCarType();
    $entry = $modelClass::create(['brand' => 'Renault']);

    $holder = lockEndpointActor(['content.car.update_any']);
    app(AcquireOrRefreshContentLock::class)($entry, $holder);

    $viewer = lockEndpointActor(['content.car.update_any']);

    $response = $this->actingAs($viewer, 'baobab')
        ->get(route('admin.content.edit', ['contentType' => 'cars', 'entry' => $entry->id]))
        ->assertOk();

    $response->assertSee($holder->name, false);
});

it('acquires the lock for the first visitor on edit', function () {
    [, $modelClass] = lockEndpointCarType();
    $entry = $modelClass::create(['brand' => 'Renault']);
    $actor = lockEndpointActor(['content.car.update_any']);

    $this->actingAs($actor, 'baobab')
        ->get(route('admin.content.edit', ['contentType' => 'cars', 'entry' => $entry->id]))
        ->assertOk();

    expect(ContentLock::sole()->user_id)->toBe($actor->id);
});

it('reports locked:false on heartbeat for the current holder', function () {
    [, $modelClass] = lockEndpointCarType();
    $entry = $modelClass::create(['brand' => 'Renault']);
    $actor = lockEndpointActor(['content.car.update_any']);

    app(AcquireOrRefreshContentLock::class)($entry, $actor);

    $this->actingAs($actor, 'baobab')
        ->postJson(route('admin.content.lock.heartbeat', ['contentType' => 'cars', 'entry' => $entry->id]))
        ->assertOk()
        ->assertJson(['locked' => false]);
});

it('reports locked:true on heartbeat once evicted by a take-over', function () {
    [, $modelClass] = lockEndpointCarType();
    $entry = $modelClass::create(['brand' => 'Renault']);
    $original = lockEndpointActor(['content.car.update_any']);
    $other = lockEndpointActor(['content.car.update_any']);

    app(AcquireOrRefreshContentLock::class)($entry, $original);

    $this->actingAs($other, 'baobab')
        ->post(route('admin.content.lock.take-over', ['contentType' => 'cars', 'entry' => $entry->id]))
        ->assertRedirect();

    $this->actingAs($original, 'baobab')
        ->postJson(route('admin.content.lock.heartbeat', ['contentType' => 'cars', 'entry' => $entry->id]))
        ->assertOk()
        ->assertJson(['locked' => true]);
});

it('denies take-over without update_any', function () {
    [, $modelClass] = lockEndpointCarType();
    $entry = $modelClass::create(['brand' => 'Renault']);
    $holder = lockEndpointActor(['content.car.update_any']);
    $bystander = lockEndpointActor(['content.car.update']);

    app(AcquireOrRefreshContentLock::class)($entry, $holder);

    $this->actingAs($bystander, 'baobab')
        ->post(route('admin.content.lock.take-over', ['contentType' => 'cars', 'entry' => $entry->id]))
        ->assertForbidden();
});

it('releases the lock on demand', function () {
    [, $modelClass] = lockEndpointCarType();
    $entry = $modelClass::create(['brand' => 'Renault']);
    $actor = lockEndpointActor(['content.car.update_any']);

    app(AcquireOrRefreshContentLock::class)($entry, $actor);

    $this->actingAs($actor, 'baobab')
        ->postJson(route('admin.content.lock.release', ['contentType' => 'cars', 'entry' => $entry->id]))
        ->assertOk()
        ->assertJson(['released' => true]);

    expect(ContentLock::count())->toBe(0);
});
