<?php

use Baobab\Access\Actions\GrantPermission;
use Baobab\Studio\Models\ModuleBlueprintDraft;
use Baobab\Users\Models\User;

/**
 * @param  list<string>  $permissions
 */
function studioPoliciesActor(array $permissions = ['baobab.system.studio.manage']): User
{
    static $counter = 0;
    $counter++;

    $user = User::create([
        'name' => "Studio Policies Actor {$counter}",
        'email' => "studio-policies-actor-{$counter}@example.com",
        'password' => 'secret',
    ]);

    app(GrantPermission::class)($user, 'baobab.admin.access');

    foreach ($permissions as $permission) {
        app(GrantPermission::class)($user, $permission);
    }

    return $user;
}

/**
 * @param  array<string, mixed>  $permissions
 */
function studioPoliciesDraft(array $permissions = ['auto_crud' => true, 'custom' => []]): ModuleBlueprintDraft
{
    // `vendor_slug` est unique en base : un test qui compare deux brouillons
    // (cf. l'avertissement `auto_crud`) en crée forcément deux distincts.
    static $counter = 0;
    $counter++;

    return ModuleBlueprintDraft::create([
        'vendor_slug' => "acme/fleet-{$counter}",
        'title' => 'Fleet',
        'current_step' => 5,
        'blueprint' => [
            'blueprint_version' => 1,
            'identity' => ['name' => 'acme/fleet', 'title' => 'Fleet', 'version' => '1.0.0', 'type' => 'module'],
            'entities' => [['key' => 'Car', 'table' => 'cars', 'fields' => [], 'relations' => []]],
            'permissions' => $permissions,
        ],
    ]);
}

it('renders the derived policy mapping, file path included', function () {
    $draft = studioPoliciesDraft();

    $this->actingAs(studioPoliciesActor(), 'baobab')
        ->get(route('admin.studio.step.show', [$draft, 5]))
        ->assertOk()
        ->assertSee('src/Policies/CarPolicy.php', false)
        ->assertSee('viewAny()', false)
        ->assertSee('fleet.cars.update', false);
});

it('adds one policy method per custom permission of that entity', function () {
    $draft = studioPoliciesDraft([
        'auto_crud' => true,
        'custom' => [['entity' => 'Car', 'key' => 'publish', 'label' => 'Publier']],
    ]);

    $this->actingAs(studioPoliciesActor(), 'baobab')
        ->get(route('admin.studio.step.show', [$draft, 5]))
        ->assertOk()
        ->assertSee('publish()', false)
        ->assertSee('fleet.cars.publish', false);
});

/**
 * La policy générée mappe toujours les cinq méthodes CRUD, y compris quand
 * l'étape 3 a coupé `auto_crud` — les chaînes n'existent alors dans aucun
 * rôle. L'écran doit le dire plutôt que de le laisser découvrir à
 * l'installation.
 */
it('warns when auto_crud is off, and stays quiet otherwise', function () {
    $actor = studioPoliciesActor();

    $this->actingAs($actor, 'baobab')
        ->get(route('admin.studio.step.show', [studioPoliciesDraft(['auto_crud' => false, 'custom' => []]), 5]))
        ->assertOk()
        ->assertSee(__('baobab::admin.studio.policies.auto_crud_disabled_warning'));

    $this->actingAs($actor, 'baobab')
        ->get(route('admin.studio.step.show', [studioPoliciesDraft(), 5]))
        ->assertOk()
        ->assertDontSee(__('baobab::admin.studio.policies.auto_crud_disabled_warning'));
});

it('advances the wizard without touching the blueprint', function () {
    $draft = studioPoliciesDraft();
    $before = $draft->blueprint;

    $this->actingAs(studioPoliciesActor(), 'baobab')
        ->post(route('admin.studio.step.update', [$draft, 5]))
        ->assertRedirect();

    expect($draft->fresh()->blueprint)->toBe($before)
        ->and($draft->fresh()->current_step)->toBe(5);
});

it('denies the policies step without the studio permission', function () {
    $draft = studioPoliciesDraft();

    $this->actingAs(studioPoliciesActor([]), 'baobab')
        ->get(route('admin.studio.step.show', [$draft, 5]))
        ->assertForbidden();
});
