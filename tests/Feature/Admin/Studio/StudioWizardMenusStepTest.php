<?php

use Baobab\Access\Actions\GrantPermission;
use Baobab\Studio\Models\ModuleBlueprintDraft;
use Baobab\Users\Models\User;
use Illuminate\Testing\TestResponse;

/**
 * @param  list<string>  $permissions
 */
function studioMenusActor(array $permissions = ['baobab.system.studio.manage']): User
{
    static $counter = 0;
    $counter++;

    $user = User::create([
        'name' => "Studio Menus Actor {$counter}",
        'email' => "studio-menus-actor-{$counter}@example.com",
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
function studioMenusDraft(array $extraBlueprint = []): ModuleBlueprintDraft
{
    static $counter = 0;
    $counter++;

    return ModuleBlueprintDraft::create([
        'vendor_slug' => "acme/fleet-{$counter}",
        'title' => 'Fleet',
        'current_step' => 6,
        'blueprint' => [
            'blueprint_version' => 1,
            'identity' => ['name' => 'acme/fleet', 'title' => 'Fleet', 'version' => '1.0.0', 'type' => 'module'],
            'entities' => [[
                'key' => 'Car',
                'table' => 'cars',
                'fields' => [],
                'relations' => [],
                'routes' => ['admin' => true, 'front' => false, 'api' => false],
            ]],
            'permissions' => ['auto_crud' => true, 'custom' => []],
            ...$extraBlueprint,
        ],
    ]);
}

/**
 * @param  list<array<string, mixed>>  $items
 */
function postMenus(ModuleBlueprintDraft $draft, User $actor, array $items): TestResponse
{
    return test()->actingAs($actor, 'baobab')->post(
        route('admin.studio.step.update', [$draft, 6]),
        ['menus' => (string) json_encode($items)]
    );
}

it('offers the route names and permissions the earlier steps will actually produce', function () {
    $draft = studioMenusDraft();

    $this->actingAs(studioMenusActor(), 'baobab')
        ->get(route('admin.studio.step.show', [$draft, 6]))
        ->assertOk()
        // `x-data` et non la seule fonction JS : le bloc `@once` du script est
        // émis dans tous les cas, seul le `x-data` prouve que le constructeur
        // est réellement instancié.
        ->assertSee('x-data="studioMenus(', false)
        // Nom de route tel que le fragment de routes admin le montera.
        ->assertSee('admin.fleet.cars.index', false)
        // Permission telle que l'étape 3 l'a déclarée.
        ->assertSee('fleet.cars.view', false);
});

it('does not offer an admin route for an entity whose admin surface is off', function () {
    $draft = studioMenusDraft([
        'entities' => [[
            'key' => 'Car',
            'table' => 'cars',
            'fields' => [],
            'relations' => [],
            'routes' => ['admin' => false, 'front' => true, 'api' => false],
        ]],
    ]);

    $this->actingAs(studioMenusActor(), 'baobab')
        ->get(route('admin.studio.step.show', [$draft, 6]))
        ->assertOk()
        ->assertDontSee('admin.fleet.cars.index', false);
});

it('saves an entry with its children', function () {
    $draft = studioMenusDraft();

    postMenus($draft, studioMenusActor(), [[
        'label' => 'Flotte',
        'icon' => 'bi-car-front',
        'route' => '',
        'permission' => 'fleet.cars.view',
        'order' => '10',
        'children' => [
            ['label' => 'Voitures', 'route' => 'admin.fleet.cars.index', 'permission' => 'fleet.cars.view', 'icon' => '', 'order' => ''],
        ],
    ]])->assertRedirect();

    $menus = $draft->fresh()->blueprint['menus']['admin'];

    expect($menus)->toHaveCount(1)
        ->and($menus[0]['label'])->toBe('Flotte')
        ->and($menus[0]['icon'])->toBe('bi-car-front')
        ->and($menus[0]['order'])->toBe(10)
        // Une clé laissée vide ne part pas dans le blueprint.
        ->and($menus[0])->not->toHaveKey('route')
        ->and($menus[0]['children'][0]['route'])->toBe('admin.fleet.cars.index');
});

it('drops an entry left without a label, the only required key', function () {
    $draft = studioMenusDraft();

    postMenus($draft, studioMenusActor(), [
        ['label' => '', 'icon' => 'bi-x', 'route' => '', 'permission' => '', 'order' => '', 'children' => []],
        ['label' => 'Flotte', 'icon' => '', 'route' => '', 'permission' => '', 'order' => '', 'children' => []],
    ])->assertRedirect();

    expect($draft->fresh()->blueprint['menus']['admin'])->toHaveCount(1);
});

it('removes the menus block entirely when the last entry is deleted', function () {
    $draft = studioMenusDraft(['menus' => ['admin' => [['label' => 'Flotte']]]]);

    postMenus($draft, studioMenusActor(), [])->assertRedirect();

    expect($draft->fresh()->blueprint)->not->toHaveKey('menus');
});

/**
 * Le constructeur ne rend que deux niveaux. Un blueprint plus profond
 * (édité à la main) doit basculer l'écran en lecture seule plutôt que de
 * perdre silencieusement le troisième niveau.
 */
it('refuses to render the builder, and saves nothing, when the blueprint is deeper than two levels', function () {
    $deep = ['admin' => [[
        'label' => 'Flotte',
        'children' => [[
            'label' => 'Voitures',
            'children' => [['label' => 'Berlines', 'route' => 'admin.fleet.cars.index']],
        ]],
    ]]];

    $draft = studioMenusDraft(['menus' => $deep]);
    $actor = studioMenusActor();

    $this->actingAs($actor, 'baobab')
        ->get(route('admin.studio.step.show', [$draft, 6]))
        ->assertOk()
        ->assertSee(__('baobab::admin.studio.menus.too_deep'))
        ->assertDontSee('x-data="studioMenus(', false);

    // Même une soumission forgée ne doit pas écraser le bloc.
    postMenus($draft, $actor, [['label' => 'Écrasé', 'children' => []]])->assertRedirect();

    expect($draft->fresh()->blueprint['menus'])->toBe($deep);
});

it('re-displays the submitted menus after a rejected save', function () {
    $draft = studioMenusDraft([
        'permissions' => ['auto_crud' => true, 'custom' => [['entity' => 'Ghost', 'key' => 'publish', 'label' => 'Publier']]],
    ]);

    $actor = studioMenusActor();
    $stepUrl = route('admin.studio.step.show', [$draft, 6]);

    $this->actingAs($actor, 'baobab')
        ->from($stepUrl)
        ->post(route('admin.studio.step.update', [$draft, 6]), [
            'menus' => (string) json_encode([['label' => 'Atelier', 'children' => []]]),
        ])
        ->assertSessionHasErrors('blueprint')
        ->assertRedirect($stepUrl);

    expect($draft->fresh()->blueprint)->not->toHaveKey('menus');

    $this->actingAs($actor, 'baobab')
        ->get($stepUrl)
        ->assertOk()
        ->assertSee('Atelier', false);
});

it('rejects malformed JSON without a server error', function () {
    $draft = studioMenusDraft();

    $this->actingAs(studioMenusActor(), 'baobab')
        ->post(route('admin.studio.step.update', [$draft, 6]), ['menus' => '{not json'])
        ->assertSessionHasErrors('blueprint');
});

it('denies the menus step without the studio permission', function () {
    $draft = studioMenusDraft();

    $this->actingAs(studioMenusActor([]), 'baobab')
        ->get(route('admin.studio.step.show', [$draft, 6]))
        ->assertForbidden();
});
