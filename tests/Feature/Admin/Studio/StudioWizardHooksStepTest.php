<?php

use Baobab\Access\Actions\GrantPermission;
use Baobab\Studio\Models\ModuleBlueprintDraft;
use Baobab\Users\Models\User;
use Illuminate\Testing\TestResponse;

/**
 * @param  list<string>  $permissions
 */
function studioHooksActor(array $permissions = ['baobab.system.studio.manage']): User
{
    static $counter = 0;
    $counter++;

    $user = User::create([
        'name' => "Studio Hooks Actor {$counter}",
        'email' => "studio-hooks-actor-{$counter}@example.com",
        'password' => 'secret',
    ]);

    app(GrantPermission::class)($user, 'baobab.admin.access');

    foreach ($permissions as $permission) {
        app(GrantPermission::class)($user, $permission);
    }

    return $user;
}

/**
 * @param  array<string, mixed>  $extraBlueprint
 */
function studioHooksDraft(array $extraBlueprint = []): ModuleBlueprintDraft
{
    static $counter = 0;
    $counter++;

    return ModuleBlueprintDraft::create([
        'vendor_slug' => "acme/fleet-{$counter}",
        'title' => 'Fleet',
        'current_step' => 8,
        'blueprint' => [
            'blueprint_version' => 1,
            'identity' => ['name' => 'acme/fleet', 'title' => 'Fleet', 'version' => '1.0.0', 'type' => 'module'],
            'entities' => [['key' => 'Car', 'table' => 'cars', 'fields' => [], 'relations' => []]],
            ...$extraBlueprint,
        ],
    ]);
}

/**
 * @param  list<array<string, string>>  $listens
 */
function postHooks(ModuleBlueprintDraft $draft, User $actor, string $emits, array $listens): TestResponse
{
    return test()->actingAs($actor, 'baobab')->post(
        route('admin.studio.step.update', [$draft, 8]),
        ['emits' => $emits, 'listens' => (string) json_encode($listens)]
    );
}

it('suggests the module slug as hook prefix and offers the documented hook catalogue', function () {
    $draft = studioHooksDraft();

    $this->actingAs(studioHooksActor(), 'baobab')
        ->get(route('admin.studio.step.show', [$draft, 8]))
        ->assertOk()
        ->assertSee('studioHooks(', false)
        ->assertSee('fleet.car.serviced', false)
        ->assertSee('baobab-hook-catalogue', false)
        // Catalogue réel : les hooks Core documentés pour les webhooks.
        ->assertSee(config('baobab.webhooks.hooks')[0], false);
});

it('saves emitted hooks one per line and listened hooks as a hook to class map', function () {
    $draft = studioHooksDraft();

    postHooks($draft, studioHooksActor(), "fleet.car.serviced\n\n  fleet.car.sold  ", [
        ['hook' => 'baobab.content.saved', 'class_name' => 'SyncCarIndex'],
    ])->assertRedirect();

    $hooks = $draft->fresh()->blueprint['hooks'];

    expect($hooks['emits'])->toBe(['fleet.car.serviced', 'fleet.car.sold'])
        ->and($hooks['listens'])->toBe(['baobab.content.saved' => 'SyncCarIndex']);
});

it('drops a listened row missing either side, since it could wire nothing', function () {
    $draft = studioHooksDraft();

    postHooks($draft, studioHooksActor(), '', [
        ['hook' => 'baobab.content.saved', 'class_name' => ''],
        ['hook' => '', 'class_name' => 'Orphan'],
        ['hook' => 'baobab.content.deleted', 'class_name' => 'CleanUp'],
    ])->assertRedirect();

    expect($draft->fresh()->blueprint['hooks']['listens'])->toBe(['baobab.content.deleted' => 'CleanUp']);
});

it('keeps the last class when the same hook is listened twice', function () {
    $draft = studioHooksDraft();

    postHooks($draft, studioHooksActor(), '', [
        ['hook' => 'baobab.content.saved', 'class_name' => 'First'],
        ['hook' => 'baobab.content.saved', 'class_name' => 'Second'],
    ])->assertRedirect();

    expect($draft->fresh()->blueprint['hooks']['listens'])->toBe(['baobab.content.saved' => 'Second']);
});

it('omits an empty side and removes the hooks block when both are empty', function () {
    $draft = studioHooksDraft();
    $actor = studioHooksActor();

    postHooks($draft, $actor, 'fleet.car.serviced', [])->assertRedirect();

    $hooks = $draft->fresh()->blueprint['hooks'];

    expect($hooks)->toHaveKey('emits')
        ->and($hooks)->not->toHaveKey('listens');

    postHooks($draft, $actor, '', [])->assertRedirect();

    expect($draft->fresh()->blueprint)->not->toHaveKey('hooks');
});

it('re-displays the submitted listeners after a rejected save', function () {
    $draft = studioHooksDraft([
        'permissions' => ['auto_crud' => true, 'custom' => [['entity' => 'Ghost', 'key' => 'publish', 'label' => 'Publier']]],
    ]);

    $actor = studioHooksActor();
    $stepUrl = route('admin.studio.step.show', [$draft, 8]);

    $this->actingAs($actor, 'baobab')
        ->from($stepUrl)
        ->post(route('admin.studio.step.update', [$draft, 8]), [
            'emits' => '',
            'listens' => (string) json_encode([['hook' => 'baobab.content.saved', 'class_name' => 'SyncCarIndex']]),
        ])
        ->assertSessionHasErrors('blueprint')
        ->assertRedirect($stepUrl);

    expect($draft->fresh()->blueprint)->not->toHaveKey('hooks');

    $this->actingAs($actor, 'baobab')
        ->get($stepUrl)
        ->assertOk()
        ->assertSee('SyncCarIndex', false);
});

it('rejects malformed JSON without a server error', function () {
    $draft = studioHooksDraft();

    $this->actingAs(studioHooksActor(), 'baobab')
        ->post(route('admin.studio.step.update', [$draft, 8]), ['emits' => '', 'listens' => '{not json'])
        ->assertSessionHasErrors('blueprint');
});

it('denies the hooks step without the studio permission', function () {
    $draft = studioHooksDraft();

    $this->actingAs(studioHooksActor([]), 'baobab')
        ->get(route('admin.studio.step.show', [$draft, 8]))
        ->assertForbidden();
});
