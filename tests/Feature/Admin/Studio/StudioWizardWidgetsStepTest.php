<?php

use Baobab\Access\Actions\GrantPermission;
use Baobab\Studio\Models\ModuleBlueprintDraft;
use Baobab\Users\Models\User;
use Illuminate\Testing\TestResponse;

/**
 * @param  list<string>  $permissions
 */
function studioWidgetsActor(array $permissions = ['baobab.system.studio.manage']): User
{
    static $counter = 0;
    $counter++;

    $user = User::create([
        'name' => "Studio Widgets Actor {$counter}",
        'email' => "studio-widgets-actor-{$counter}@example.com",
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
function studioWidgetsDraft(array $extraBlueprint = []): ModuleBlueprintDraft
{
    static $counter = 0;
    $counter++;

    return ModuleBlueprintDraft::create([
        'vendor_slug' => "acme/fleet-{$counter}",
        'title' => 'Fleet',
        'current_step' => 7,
        'blueprint' => [
            'blueprint_version' => 1,
            'identity' => ['name' => 'acme/fleet', 'title' => 'Fleet', 'version' => '1.0.0', 'type' => 'module'],
            'entities' => [['key' => 'Car', 'table' => 'cars', 'fields' => [], 'relations' => []]],
            ...$extraBlueprint,
        ],
    ]);
}

/**
 * @param  list<array<string, mixed>>  $widgets
 */
function postWidgets(ModuleBlueprintDraft $draft, User $actor, array $widgets): TestResponse
{
    return test()->actingAs($actor, 'baobab')->post(
        route('admin.studio.step.update', [$draft, 7]),
        ['widgets' => (string) json_encode($widgets)]
    );
}

it('renders the widgets step with the field type catalogue and no zone picker', function () {
    $draft = studioWidgetsDraft();

    $this->actingAs(studioWidgetsActor(), 'baobab')
        ->get(route('admin.studio.step.show', [$draft, 7]))
        ->assertOk()
        ->assertSee('studioWidgets(', false)
        // Les zones appartiennent au thème actif, pas au blueprint.
        ->assertSee(__('baobab::admin.studio.widgets.zones_hint'));
});

it('saves a widget with its settings fields', function () {
    $draft = studioWidgetsDraft();

    postWidgets($draft, studioWidgetsActor(), [[
        'key' => 'fleet.latest-cars',
        'label' => 'Dernières voitures',
        'class_name' => 'LatestCars',
        'cache_ttl' => '300',
        'settings_fields' => [
            ['key' => 'limit', 'type' => 'integer', 'required' => true],
            ['key' => 'mode', 'type' => 'select', 'required' => false, 'options' => ['choices' => ['grid', 'list', '  ']]],
        ],
    ]])->assertRedirect();

    $widgets = $draft->fresh()->blueprint['widgets'];

    expect($widgets)->toHaveCount(1)
        ->and($widgets[0]['key'])->toBe('fleet.latest-cars')
        ->and($widgets[0]['class_name'])->toBe('LatestCars')
        ->and($widgets[0]['cache_ttl'])->toBe(300)
        ->and($widgets[0]['settings_fields'][0]['required'])->toBeTrue()
        // Même normalisation de champs qu'à l'étape 2 (BlueprintFields partagé).
        ->and($widgets[0]['settings_fields'][1]['options']['choices'])->toBe(['grid', 'list']);
});

it('omits cache_ttl and settings_fields when they are left empty', function () {
    $draft = studioWidgetsDraft();

    postWidgets($draft, studioWidgetsActor(), [[
        'key' => 'fleet.promo',
        'label' => 'Promo',
        'class_name' => 'Promo',
        'cache_ttl' => '',
        'settings_fields' => [],
    ]])->assertRedirect();

    $widget = $draft->fresh()->blueprint['widgets'][0];

    expect($widget)->not->toHaveKey('cache_ttl')
        ->and($widget)->not->toHaveKey('settings_fields');
});

it('drops a row abandoned entirely, and keeps a half-filled one for the cross-validation to judge', function () {
    $draft = studioWidgetsDraft();

    postWidgets($draft, studioWidgetsActor(), [
        ['key' => '', 'label' => '', 'class_name' => '', 'cache_ttl' => '', 'settings_fields' => []],
        ['key' => 'fleet.promo', 'label' => 'Promo', 'class_name' => 'Promo', 'cache_ttl' => '', 'settings_fields' => []],
    ])->assertRedirect();

    expect($draft->fresh()->blueprint['widgets'])->toHaveCount(1);
});

it('removes the widgets block entirely when the last widget is deleted', function () {
    $draft = studioWidgetsDraft([
        'widgets' => [['key' => 'fleet.promo', 'label' => 'Promo', 'class_name' => 'Promo']],
    ]);

    postWidgets($draft, studioWidgetsActor(), [])->assertRedirect();

    expect($draft->fresh()->blueprint)->not->toHaveKey('widgets');
});

it('rejects an unknown settings field type through the shared cross-validation', function () {
    $draft = studioWidgetsDraft();

    postWidgets($draft, studioWidgetsActor(), [[
        'key' => 'fleet.promo',
        'label' => 'Promo',
        'class_name' => 'Promo',
        'cache_ttl' => '',
        'settings_fields' => [['key' => 'limit', 'type' => 'nope']],
    ]])->assertSessionHasErrors('blueprint');

    expect($draft->fresh()->blueprint)->not->toHaveKey('widgets');
});

it('re-displays the submitted widgets after a rejected save', function () {
    $draft = studioWidgetsDraft();
    $actor = studioWidgetsActor();
    $stepUrl = route('admin.studio.step.show', [$draft, 7]);

    $this->actingAs($actor, 'baobab')
        ->from($stepUrl)
        ->post(route('admin.studio.step.update', [$draft, 7]), [
            'widgets' => (string) json_encode([[
                'key' => 'fleet.spaceship',
                'label' => 'Vaisseau',
                'class_name' => 'Spaceship',
                'cache_ttl' => '',
                'settings_fields' => [['key' => 'hull', 'type' => 'nope']],
            ]]),
        ])
        ->assertRedirect($stepUrl);

    $this->actingAs($actor, 'baobab')
        ->get($stepUrl)
        ->assertOk()
        ->assertSee('Spaceship', false);
});

it('denies the widgets step without the studio permission', function () {
    $draft = studioWidgetsDraft();

    $this->actingAs(studioWidgetsActor([]), 'baobab')
        ->get(route('admin.studio.step.show', [$draft, 7]))
        ->assertForbidden();
});
