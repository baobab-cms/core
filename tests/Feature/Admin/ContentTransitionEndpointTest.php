<?php

use Baobab\Access\Actions\GrantPermission;
use Baobab\ContentTypes\Actions\BuildContentType;
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
function endpointCarType(): array
{
    $contentType = app(BuildContentType::class)(contentTypeBlueprintJson('ContentTransitionEndpointEntry', [
        'workflow' => true,
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
function transitionActor(array $permissions): User
{
    static $counter = 0;
    $counter++;

    $user = User::create([
        'name' => "Transition Actor {$counter}",
        'email' => "transition-actor-{$counter}@example.com",
        'password' => 'secret',
    ]);

    app(GrantPermission::class)($user, 'baobab.admin.access');

    foreach ($permissions as $permission) {
        app(GrantPermission::class)($user, $permission);
    }

    return $user;
}

function transitionUrl(string $entry, string $transition): string
{
    return route('admin.content.transition', ['contentType' => 'content-transition-endpoint-entries', 'entry' => $entry, 'transition' => $transition]);
}

it('denies submit without the update permission', function () {
    [, $modelClass] = endpointCarType();
    $entry = $modelClass::create(['brand' => 'Renault']);
    $actor = transitionActor([]);

    $this->actingAs($actor, 'baobab')
        ->post(transitionUrl((string) $entry->id, 'submit'))
        ->assertForbidden();
});

it('allows submit for the own-content author with content.content_transition_endpoint_entry.update', function () {
    [, $modelClass] = endpointCarType();
    $actor = transitionActor(['content.content_transition_endpoint_entry.update']);
    $entry = new $modelClass(['brand' => 'Renault']);
    $entry->author_id = $actor->id;
    $entry->save();

    $this->actingAs($actor, 'baobab')
        ->post(transitionUrl((string) $entry->id, 'submit'))
        ->assertRedirect();

    expect($entry->fresh()->status)->toBe('pending');
});

it('denies approve/reject without publish_any, even with plain publish', function () {
    [, $modelClass] = endpointCarType();
    $entry = $modelClass::create(['brand' => 'Renault', 'status' => 'pending']);
    $actor = transitionActor(['content.content_transition_endpoint_entry.publish']);

    $this->actingAs($actor, 'baobab')
        ->post(transitionUrl((string) $entry->id, 'approve'))
        ->assertForbidden();
});

it('requires a comment to reject', function () {
    [, $modelClass] = endpointCarType();
    $entry = $modelClass::create(['brand' => 'Renault', 'status' => 'pending']);
    $actor = transitionActor(['content.content_transition_endpoint_entry.publish_any']);

    $this->actingAs($actor, 'baobab')
        ->post(transitionUrl((string) $entry->id, 'reject'), [])
        ->assertSessionHasErrors('comment');

    expect($entry->fresh()->status)->toBe('pending');
});

it('refuses to schedule in the past', function () {
    [, $modelClass] = endpointCarType();
    $actor = transitionActor(['content.content_transition_endpoint_entry.publish']);
    $entry = new $modelClass(['brand' => 'Renault']);
    $entry->author_id = $actor->id;
    $entry->save();

    $this->actingAs($actor, 'baobab')
        ->post(transitionUrl((string) $entry->id, 'schedule'), ['published_at' => now()->subDay()->toDateTimeString()])
        ->assertSessionHasErrors('published_at');
});

it('publishes and flashes a success toast', function () {
    [, $modelClass] = endpointCarType();
    $actor = transitionActor(['content.content_transition_endpoint_entry.publish']);
    $entry = new $modelClass(['brand' => 'Renault']);
    $entry->author_id = $actor->id;
    $entry->save();

    $this->actingAs($actor, 'baobab')
        ->post(transitionUrl((string) $entry->id, 'publish'))
        ->assertRedirect()
        ->assertSessionHas('toast');

    expect($entry->fresh()->status)->toBe('published');
});

it('flashes an error toast instead of a 500 on an illegal transition', function () {
    [, $modelClass] = endpointCarType();
    $actor = transitionActor(['content.content_transition_endpoint_entry.publish']);
    $entry = new $modelClass(['brand' => 'Renault', 'status' => 'archived']);
    $entry->author_id = $actor->id;
    $entry->save();

    $this->actingAs($actor, 'baobab')
        ->post(transitionUrl((string) $entry->id, 'publish'))
        ->assertRedirect();

    expect($entry->fresh()->status)->toBe('archived');
});
