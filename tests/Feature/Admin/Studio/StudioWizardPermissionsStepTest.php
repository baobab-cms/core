<?php

use Baobab\Access\Actions\GrantPermission;
use Baobab\Studio\Models\ModuleBlueprintDraft;
use Baobab\Users\Models\User;
use Illuminate\Testing\TestResponse;

/**
 * @param  list<string>  $permissions
 */
function studioPermissionsActor(array $permissions = ['baobab.system.studio.manage']): User
{
    static $counter = 0;
    $counter++;

    $user = User::create([
        'name' => "Studio Permissions Actor {$counter}",
        'email' => "studio-permissions-actor-{$counter}@example.com",
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
function studioPermissionsDraft(array $extraBlueprint = []): ModuleBlueprintDraft
{
    return ModuleBlueprintDraft::create([
        'vendor_slug' => 'acme/fleet',
        'title' => 'Fleet',
        'current_step' => 3,
        'blueprint' => [
            'blueprint_version' => 1,
            'identity' => ['name' => 'acme/fleet', 'title' => 'Fleet', 'version' => '1.0.0', 'type' => 'module'],
            'entities' => [
                ['key' => 'Car', 'table' => 'cars', 'fields' => [], 'relations' => []],
            ],
            ...$extraBlueprint,
        ],
    ]);
}

/**
 * @param  array<string, mixed>  $permissions
 */
function postPermissions(ModuleBlueprintDraft $draft, User $actor, array $permissions): TestResponse
{
    return test()->actingAs($actor, 'baobab')->post(
        route('admin.studio.step.update', [$draft, 3]),
        ['permissions' => (string) json_encode($permissions)]
    );
}

it('renders the permissions step showing the real permission strings', function () {
    $draft = studioPermissionsDraft();

    $this->actingAs(studioPermissionsActor(), 'baobab')
        ->get(route('admin.studio.step.show', [$draft, 3]))
        ->assertOk()
        ->assertSee('studioPermissions(', false)
        // Le préfixe affiché est celui que le générateur écrira réellement.
        ->assertSee('fleet.cars.view', false)
        ->assertSee('fleet.cars.delete', false);
});

it('saves the auto_crud switch and a custom permission', function () {
    $draft = studioPermissionsDraft();

    postPermissions($draft, studioPermissionsActor(), [
        'auto_crud' => false,
        'custom' => [['entity' => 'Car', 'key' => 'publish', 'label' => 'Publier une voiture']],
    ])->assertRedirect();

    expect($draft->fresh()->blueprint['permissions'])->toBe([
        'auto_crud' => false,
        'custom' => [['entity' => 'Car', 'key' => 'publish', 'label' => 'Publier une voiture']],
    ]);
});

it('drops an entirely empty custom row instead of failing the step', function () {
    $draft = studioPermissionsDraft();

    postPermissions($draft, studioPermissionsActor(), [
        'auto_crud' => true,
        'custom' => [
            ['entity' => '', 'key' => '', 'label' => ''],
            ['entity' => 'Car', 'key' => 'archive', 'label' => 'Archiver'],
        ],
    ])->assertRedirect();

    expect($draft->fresh()->blueprint['permissions']['custom'])->toBe([
        ['entity' => 'Car', 'key' => 'archive', 'label' => 'Archiver'],
    ]);
});

it('rejects a custom permission attached to an entity that does not exist', function () {
    $draft = studioPermissionsDraft();

    postPermissions($draft, studioPermissionsActor(), [
        'auto_crud' => true,
        'custom' => [['entity' => 'Ghost', 'key' => 'publish', 'label' => 'Publier']],
    ])->assertSessionHasErrors('blueprint');

    expect($draft->fresh()->blueprint)->not->toHaveKey('permissions');
});

it('rejects a custom permission left half-filled', function () {
    $draft = studioPermissionsDraft();

    postPermissions($draft, studioPermissionsActor(), [
        'auto_crud' => true,
        'custom' => [['entity' => 'Car', 'key' => 'publish', 'label' => '']],
    ])->assertSessionHasErrors('blueprint');

    expect($draft->fresh()->blueprint)->not->toHaveKey('permissions');
});

it('rejects the same custom permission declared twice on one entity', function () {
    $draft = studioPermissionsDraft();

    postPermissions($draft, studioPermissionsActor(), [
        'auto_crud' => true,
        'custom' => [
            ['entity' => 'Car', 'key' => 'publish', 'label' => 'Publier'],
            ['entity' => 'Car', 'key' => 'publish', 'label' => 'Publier encore'],
        ],
    ])->assertSessionHasErrors('blueprint');
});

it('re-displays the submitted permissions after a rejected save, not the stored blueprint', function () {
    $draft = studioPermissionsDraft();
    $actor = studioPermissionsActor();
    $stepUrl = route('admin.studio.step.show', [$draft, 3]);

    $this->actingAs($actor, 'baobab')
        ->from($stepUrl)
        ->post(route('admin.studio.step.update', [$draft, 3]), [
            'permissions' => (string) json_encode([
                'auto_crud' => false,
                'custom' => [['entity' => 'Ghost', 'key' => 'teleport', 'label' => 'Téléporter']],
            ]),
        ])
        ->assertRedirect($stepUrl);

    expect($draft->fresh()->blueprint)->not->toHaveKey('permissions');

    $this->actingAs($actor, 'baobab')
        ->get($stepUrl)
        ->assertOk()
        ->assertSee('teleport', false);
});

it('rejects malformed JSON without a server error', function () {
    $draft = studioPermissionsDraft();

    $this->actingAs(studioPermissionsActor(), 'baobab')
        ->post(route('admin.studio.step.update', [$draft, 3]), ['permissions' => '{not json'])
        ->assertSessionHasErrors('blueprint');
});

it('denies the permissions step without the studio permission', function () {
    $draft = studioPermissionsDraft();

    $this->actingAs(studioPermissionsActor([]), 'baobab')
        ->get(route('admin.studio.step.show', [$draft, 3]))
        ->assertForbidden();
});
