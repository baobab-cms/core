<?php

use Baobab\Access\Actions\GrantPermission;
use Baobab\Studio\Models\ModuleBlueprintDraft;
use Baobab\Users\Models\User;
use Illuminate\Testing\TestResponse;

/**
 * @param  list<string>  $permissions
 */
function studioEntitiesActor(array $permissions = ['baobab.system.studio.manage']): User
{
    static $counter = 0;
    $counter++;

    $user = User::create([
        'name' => "Studio Entities Actor {$counter}",
        'email' => "studio-entities-actor-{$counter}@example.com",
        'password' => 'secret',
    ]);

    app(GrantPermission::class)($user, 'baobab.admin.access');

    foreach ($permissions as $permission) {
        app(GrantPermission::class)($user, $permission);
    }

    return $user;
}

function studioEntitiesDraft(int $currentStep = 2): ModuleBlueprintDraft
{
    return ModuleBlueprintDraft::create([
        'vendor_slug' => 'acme/fleet',
        'title' => 'Fleet',
        'current_step' => $currentStep,
        'blueprint' => [
            'blueprint_version' => 1,
            'identity' => ['name' => 'acme/fleet', 'title' => 'Fleet', 'version' => '1.0.0', 'type' => 'module'],
        ],
    ]);
}

/**
 * @param  list<array<string, mixed>>  $entities
 */
function postEntities(ModuleBlueprintDraft $draft, User $actor, array $entities): TestResponse
{
    return test()->actingAs($actor, 'baobab')->post(
        route('admin.studio.step.update', [$draft, 2]),
        ['entities' => (string) json_encode($entities)]
    );
}

it('renders the entities step with its field type catalogue', function () {
    $draft = studioEntitiesDraft();

    $this->actingAs(studioEntitiesActor(), 'baobab')
        ->get(route('admin.studio.step.show', [$draft, 2]))
        ->assertOk()
        ->assertSee('studioEntities(', false)
        ->assertSee('core:User', false)
        ->assertSee('one_to_many', false);
});

it('saves an entity with its fields and relations', function () {
    $draft = studioEntitiesDraft();

    postEntities($draft, studioEntitiesActor(), [
        [
            'key' => 'Brand',
            'table' => 'brands',
            'options' => ['timestamps' => true, 'soft_deletes' => false, 'uuid' => false],
            'fields' => [['key' => 'name', 'type' => 'text', 'required' => true, 'unique' => false, 'indexed' => false]],
            'relations' => [],
        ],
        [
            'key' => 'Car',
            'table' => 'cars',
            'options' => ['timestamps' => true, 'soft_deletes' => true, 'uuid' => false],
            'fields' => [['key' => 'plate', 'type' => 'text', 'required' => false, 'unique' => true, 'indexed' => true]],
            'relations' => [['key' => 'brand', 'type' => 'one_to_many', 'target' => 'entity:Brand', 'on_delete' => 'cascade']],
        ],
    ])->assertRedirect();

    $entities = $draft->fresh()->blueprint['entities'];

    expect($entities)->toHaveCount(2)
        ->and($entities[1]['key'])->toBe('Car')
        ->and($entities[1]['options']['soft_deletes'])->toBeTrue()
        ->and($entities[1]['fields'][0])->toBe([
            'key' => 'plate',
            'type' => 'text',
            'required' => false,
            'unique' => true,
            'indexed' => true,
        ])
        ->and($entities[1]['relations'][0]['target'])->toBe('entity:Brand');
});

it('derives the table name from the key when left empty', function () {
    $draft = studioEntitiesDraft();

    postEntities($draft, studioEntitiesActor(), [
        ['key' => 'BlogPost', 'table' => '', 'fields' => [], 'relations' => []],
    ])->assertRedirect();

    expect($draft->fresh()->blueprint['entities'][0]['table'])->toBe('blog_posts');
});

it('keeps a select field\'s choices and drops an empty options block otherwise', function () {
    $draft = studioEntitiesDraft();

    postEntities($draft, studioEntitiesActor(), [
        [
            'key' => 'Car',
            'table' => 'cars',
            'fields' => [
                ['key' => 'status', 'type' => 'select', 'options' => ['choices' => ['draft', 'published', '  ']]],
                ['key' => 'plate', 'type' => 'text', 'options' => ['choices' => []]],
            ],
            'relations' => [],
        ],
    ])->assertRedirect();

    $fields = $draft->fresh()->blueprint['entities'][0]['fields'];

    expect($fields[0]['options']['choices'])->toBe(['draft', 'published'])
        ->and($fields[1])->not->toHaveKey('options');
});

it('preserves the routes block owned by step 4 when step 2 is re-saved', function () {
    $draft = studioEntitiesDraft();
    $draft->blueprint = [
        ...$draft->blueprint,
        'entities' => [[
            'key' => 'Car',
            'table' => 'cars',
            'fields' => [],
            'relations' => [],
            'routes' => ['admin' => true, 'front' => true, 'api' => false],
        ]],
    ];
    $draft->save();

    postEntities($draft, studioEntitiesActor(), [
        ['key' => 'Car', 'table' => 'cars', 'fields' => [['key' => 'plate', 'type' => 'text']], 'relations' => []],
    ])->assertRedirect();

    expect($draft->fresh()->blueprint['entities'][0]['routes'])->toBe(['admin' => true, 'front' => true, 'api' => false]);
});

/**
 * Défaut réel signalé en vérification navigateur de la Pass B5 : « Contenu »
 * saisi comme clé de champ traversait les huit étapes sans un mot, pour n'être
 * refusé qu'au récapitulatif — loin de l'écran où la faute avait été commise.
 * Le motif est désormais vérifié dès l'enregistrement de l'étape 2.
 */
it('rejects a human label typed into a key field, at the step where it was typed', function () {
    $draft = studioEntitiesDraft();

    postEntities($draft, studioEntitiesActor(), [
        ['key' => 'BlogPost', 'table' => 'blog_posts', 'fields' => [['key' => 'Contenu', 'type' => 'text']], 'relations' => []],
    ])->assertSessionHasErrors('blueprint');

    expect($draft->fresh()->blueprint)->not->toHaveKey('entities');
});

it('rejects a badly cased entity key or table name', function (string $key, string $table) {
    // `studioEntitiesDraft()` porte un `vendor_slug` fixe, unique en base :
    // un brouillon par cas, jamais deux dans le même test.
    postEntities(studioEntitiesDraft(), studioEntitiesActor(), [
        ['key' => $key, 'table' => $table, 'fields' => [], 'relations' => []],
    ])->assertSessionHasErrors('blueprint');
})->with([
    'clé d\'entité en snake_case' => ['blog_post', 'blog_posts'],
    'nom de table en PascalCase' => ['BlogPost', 'BlogPosts'],
]);

it('rejects an unknown field type without touching the stored draft', function () {
    $draft = studioEntitiesDraft();

    postEntities($draft, studioEntitiesActor(), [
        ['key' => 'Car', 'table' => 'cars', 'fields' => [['key' => 'plate', 'type' => 'nope']], 'relations' => []],
    ])->assertSessionHasErrors('blueprint');

    expect($draft->fresh()->blueprint)->not->toHaveKey('entities');
});

it('rejects a select field declared without choices', function () {
    $draft = studioEntitiesDraft();

    postEntities($draft, studioEntitiesActor(), [
        ['key' => 'Car', 'table' => 'cars', 'fields' => [['key' => 'status', 'type' => 'select']], 'relations' => []],
    ])->assertSessionHasErrors('blueprint');

    expect($draft->fresh()->blueprint)->not->toHaveKey('entities');
});

it('rejects a relation pointing at an entity that does not exist', function () {
    $draft = studioEntitiesDraft();

    postEntities($draft, studioEntitiesActor(), [
        [
            'key' => 'Car',
            'table' => 'cars',
            'fields' => [],
            'relations' => [['key' => 'brand', 'type' => 'one_to_many', 'target' => 'entity:Ghost']],
        ],
    ])->assertSessionHasErrors('blueprint');

    expect($draft->fresh()->blueprint)->not->toHaveKey('entities');
});

it('rejects two entities sharing the same key', function () {
    $draft = studioEntitiesDraft();

    postEntities($draft, studioEntitiesActor(), [
        ['key' => 'Car', 'table' => 'cars', 'fields' => [], 'relations' => []],
        ['key' => 'Car', 'table' => 'autos', 'fields' => [], 'relations' => []],
    ])->assertSessionHasErrors('blueprint');
});

it('rejects malformed JSON without a server error', function () {
    $draft = studioEntitiesDraft();

    $this->actingAs(studioEntitiesActor(), 'baobab')
        ->post(route('admin.studio.step.update', [$draft, 2]), ['entities' => '{not json'])
        ->assertSessionHasErrors('blueprint');
});

it('re-displays the submitted entities after a rejected save, not the stored blueprint', function () {
    $draft = studioEntitiesDraft();
    $actor = studioEntitiesActor();
    $stepUrl = route('admin.studio.step.show', [$draft, 2]);

    $this->actingAs($actor, 'baobab')
        ->from($stepUrl)
        ->post(route('admin.studio.step.update', [$draft, 2]), [
            'entities' => (string) json_encode([[
                'key' => 'Spaceship',
                'table' => 'spaceships',
                'fields' => [['key' => 'hull', 'type' => 'nope']],
                'relations' => [],
            ]]),
        ])
        ->assertRedirect($stepUrl);

    // Le brouillon n'a rien enregistré, mais l'écran doit rendre la saisie
    // refusée — sans quoi tout le travail en cours serait perdu.
    expect($draft->fresh()->blueprint)->not->toHaveKey('entities');

    $this->actingAs($actor, 'baobab')
        ->get($stepUrl)
        ->assertOk()
        ->assertSee('Spaceship', false)
        ->assertSee('hull', false);
});

it('falls back to the stored blueprint when the re-flashed input is malformed', function () {
    $draft = studioEntitiesDraft();
    $draft->blueprint = [
        ...$draft->blueprint,
        'entities' => [['key' => 'Car', 'table' => 'cars', 'fields' => [], 'relations' => []]],
    ];
    $draft->save();

    $actor = studioEntitiesActor();
    $stepUrl = route('admin.studio.step.show', [$draft, 2]);

    $this->actingAs($actor, 'baobab')
        ->from($stepUrl)
        ->post(route('admin.studio.step.update', [$draft, 2]), ['entities' => '{not json'])
        ->assertRedirect($stepUrl);

    $this->actingAs($actor, 'baobab')
        ->get($stepUrl)
        ->assertOk()
        ->assertSee('Car', false);
});

it('denies the entities step without the studio permission', function () {
    $draft = studioEntitiesDraft();

    $this->actingAs(studioEntitiesActor([]), 'baobab')
        ->get(route('admin.studio.step.show', [$draft, 2]))
        ->assertForbidden();
});

it('now advances current_step to 2 after saving the identity step', function () {
    $draft = ModuleBlueprintDraft::create([
        'vendor_slug' => 'acme/fleet',
        'title' => 'Fleet',
        'current_step' => 1,
        'blueprint' => ['blueprint_version' => 1, 'identity' => ['name' => 'acme/fleet', 'title' => 'Fleet', 'version' => '1.0.0', 'type' => 'module']],
    ]);

    $this->actingAs(studioEntitiesActor(), 'baobab')
        ->post(route('admin.studio.step.update', [$draft, 1]), [
            'name' => 'acme/fleet',
            'title' => 'Fleet',
            'version' => '1.0.0',
        ])
        ->assertRedirect();

    expect($draft->fresh()->current_step)->toBe(2);
});
