<?php

use Baobab\Access\Actions\GrantPermission;
use Baobab\Studio\Models\ModuleBlueprintDraft;
use Baobab\Users\Models\User;
use Illuminate\Testing\TestResponse;

/**
 * @param  list<string>  $permissions
 */
function studioRoutesActor(array $permissions = ['baobab.system.studio.manage']): User
{
    static $counter = 0;
    $counter++;

    $user = User::create([
        'name' => "Studio Routes Actor {$counter}",
        'email' => "studio-routes-actor-{$counter}@example.com",
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
function studioRoutesDraft(array $extraBlueprint = []): ModuleBlueprintDraft
{
    return ModuleBlueprintDraft::create([
        'vendor_slug' => 'acme/fleet',
        'title' => 'Fleet',
        'current_step' => 4,
        'blueprint' => [
            'blueprint_version' => 1,
            'identity' => ['name' => 'acme/fleet', 'title' => 'Fleet', 'version' => '1.0.0', 'type' => 'module'],
            'entities' => [
                ['key' => 'Car', 'table' => 'cars', 'fields' => [], 'relations' => []],
                ['key' => 'Brand', 'table' => 'brands', 'fields' => [], 'relations' => []],
            ],
            ...$extraBlueprint,
        ],
    ]);
}

/**
 * @param  array<string, array<string, string>>  $routes
 */
function postRoutes(ModuleBlueprintDraft $draft, User $actor, array $routes): TestResponse
{
    return test()->actingAs($actor, 'baobab')->post(
        route('admin.studio.step.update', [$draft, 4]),
        ['routes' => $routes]
    );
}

it('renders the routes step showing the URIs and files each surface produces', function () {
    $draft = studioRoutesDraft();

    $this->actingAs(studioRoutesActor(), 'baobab')
        ->get(route('admin.studio.step.show', [$draft, 4]))
        ->assertOk()
        // URIs réellement montées par les fragments de routes de la Pass A2.
        ->assertSee('/admin/fleet/cars', false)
        ->assertSee('/api/v1/fleet/cars', false)
        // Fichiers réellement écrits par le générateur.
        ->assertSee('src/Http/Controllers/Front/CarController.php', false)
        ->assertSee('resources/views/cars/front-show.blade.php', false);
});

it('writes an explicit routes block per entity, unchecked surfaces included', function () {
    $draft = studioRoutesDraft();

    postRoutes($draft, studioRoutesActor(), [
        'Car' => ['admin' => '1', 'front' => '1', 'api' => '0'],
        'Brand' => ['admin' => '0', 'front' => '0', 'api' => '1'],
    ])->assertRedirect();

    $entities = $draft->fresh()->blueprint['entities'];

    expect($entities[0]['routes'])->toBe(['admin' => true, 'front' => true, 'api' => false])
        ->and($entities[1]['routes'])->toBe(['admin' => false, 'front' => false, 'api' => true]);
});

it('falls back to the generator defaults for an entity missing from the submission', function () {
    $draft = studioRoutesDraft();

    postRoutes($draft, studioRoutesActor(), [
        'Car' => ['admin' => '0', 'front' => '0', 'api' => '0'],
    ])->assertRedirect();

    $entities = $draft->fresh()->blueprint['entities'];

    expect($entities[0]['routes'])->toBe(['admin' => false, 'front' => false, 'api' => false])
        ->and($entities[1]['routes'])->toBe(['admin' => true, 'front' => false, 'api' => false]);
});

it('keeps the fields and relations owned by step 2 untouched', function () {
    $draft = studioRoutesDraft([
        'entities' => [[
            'key' => 'Car',
            'table' => 'cars',
            'fields' => [['key' => 'plate', 'type' => 'text']],
            'relations' => [],
        ]],
    ]);

    postRoutes($draft, studioRoutesActor(), ['Car' => ['admin' => '1', 'front' => '0', 'api' => '0']])
        ->assertRedirect();

    expect($draft->fresh()->blueprint['entities'][0]['fields'])->toBe([['key' => 'plate', 'type' => 'text']]);
});

it('pre-checks the surfaces already stored on the draft', function () {
    $draft = studioRoutesDraft([
        'entities' => [[
            'key' => 'Car',
            'table' => 'cars',
            'fields' => [],
            'relations' => [],
            'routes' => ['admin' => false, 'front' => true, 'api' => false],
        ]],
    ]);

    $this->actingAs(studioRoutesActor(), 'baobab')
        ->get(route('admin.studio.step.show', [$draft, 4]))
        ->assertOk()
        ->assertSee('name="routes[Car][front]" value="1" checked', false)
        ->assertDontSee('name="routes[Car][admin]" value="1" checked', false);
});

/**
 * La cross-validation vaut pour le blueprint entier, pas seulement pour
 * l'étape soumise : un brouillon rendu incohérent hors de l'écran (ici une
 * permission personnalisée orpheline, injectée directement en base) fait
 * échouer l'enregistrement de l'étape 4 — et la saisie de l'étape 4 doit
 * malgré tout être réaffichée telle qu'elle a été soumise.
 */
it('re-displays the submitted surfaces after a rejected save', function () {
    $draft = studioRoutesDraft([
        'permissions' => ['auto_crud' => true, 'custom' => [['entity' => 'Ghost', 'key' => 'publish', 'label' => 'Publier']]],
    ]);

    $actor = studioRoutesActor();
    $stepUrl = route('admin.studio.step.show', [$draft, 4]);

    $this->actingAs($actor, 'baobab')
        ->from($stepUrl)
        ->post(route('admin.studio.step.update', [$draft, 4]), [
            'routes' => ['Car' => ['admin' => '0', 'front' => '1', 'api' => '0']],
        ])
        ->assertSessionHasErrors('blueprint')
        ->assertRedirect($stepUrl);

    expect($draft->fresh()->blueprint['entities'][0])->not->toHaveKey('routes');

    $this->actingAs($actor, 'baobab')
        ->get($stepUrl)
        ->assertOk()
        ->assertSee('name="routes[Car][front]" value="1" checked', false)
        ->assertDontSee('name="routes[Car][admin]" value="1" checked', false);
});

it('denies the routes step without the studio permission', function () {
    $draft = studioRoutesDraft();

    $this->actingAs(studioRoutesActor([]), 'baobab')
        ->get(route('admin.studio.step.show', [$draft, 4]))
        ->assertForbidden();
});
