<?php

use Baobab\ContentTypes\Actions\BuildContentType;
use Baobab\Modules\Models\Module;
use Baobab\Modules\ModuleAutoloader;
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

it('serves a published entry of a public addressable type without authentication', function () {
    [, $carClass] = buildApiArticle();
    $carClass::create(['brand' => 'Peugeot', 'price' => 25000, 'internal_note' => '', 'slug' => 'peugeot', 'status' => 'published']);

    $response = $this->getJson('/api/v1/content/api-articles')->assertOk();

    $response->assertJsonPath('data.0.brand', 'Peugeot')
        ->assertJsonPath('meta.pagination.total', 1)
        ->assertJsonStructure(['data', 'meta' => ['pagination' => ['total', 'per_page', 'current_page']], 'links' => ['next', 'prev']]);
});

it('never serializes a field not marked exposed_in_api', function () {
    [, $carClass] = buildApiArticle();
    $carClass::create(['brand' => 'Peugeot', 'price' => 25000, 'internal_note' => 'secret', 'slug' => 'peugeot', 'status' => 'published']);

    $this->getJson('/api/v1/content/api-articles')
        ->assertOk()
        ->assertJsonMissingPath('data.0.internal_note');
});

it('requires authentication to read a draft entry even on a public type', function () {
    [, $carClass] = buildApiArticle();
    $carClass::create(['brand' => 'Peugeot', 'price' => 25000, 'internal_note' => '', 'slug' => 'peugeot', 'status' => 'draft']);

    $this->getJson('/api/v1/content/api-articles')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('denies public read entirely when public_api_read is disabled on the type', function () {
    [, $carClass] = buildApiArticle(['public_api_read' => false]);
    $carClass::create(['brand' => 'Peugeot', 'price' => 25000, 'internal_note' => '', 'slug' => 'peugeot', 'status' => 'published']);

    $this->getJson('/api/v1/content/api-articles')
        ->assertStatus(401)
        ->assertJsonPath('type', 'https://docs.baobabcms.com/errors/unauthenticated')
        ->assertJsonPath('status', 401);
});

it('lets an authenticated actor with viewAny read a type with public_api_read disabled', function () {
    [, $carClass] = buildApiArticle(['public_api_read' => false]);
    $carClass::create(['brand' => 'Peugeot', 'price' => 25000, 'internal_note' => '', 'slug' => 'peugeot', 'status' => 'published']);
    $actor = apiActor(['content.api_article.view']);

    $this->actingAs($actor, 'baobab')
        ->getJson('/api/v1/content/api-articles')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('returns 401 without a session and 403 with a session lacking permission on a non-addressable type', function () {
    $contentType = app(BuildContentType::class)((string) json_encode([
        'key' => 'ApiInternal',
        'label' => ['singular' => 'Interne', 'plural' => 'Internes'],
        'fields' => [['key' => 'note', 'type' => 'text']],
    ]));
    $module = Module::findOrFail($contentType->module_id);
    app(ModuleAutoloader::class)->registerFor($module);

    $this->getJson('/api/v1/content/api-internals')->assertStatus(401);

    $actor = apiActor([]);
    $this->actingAs($actor, 'baobab')
        ->getJson('/api/v1/content/api-internals')
        ->assertStatus(403)
        ->assertJsonPath('type', 'https://docs.baobabcms.com/errors/forbidden');
});

it('returns 404 RFC 9457 for an unknown content type', function () {
    $this->getJson('/api/v1/content/does-not-exist')
        ->assertStatus(404)
        ->assertJsonPath('type', 'https://docs.baobabcms.com/errors/not-found')
        ->assertJsonPath('status', 404);
});

it('returns 404 for an unknown entry', function () {
    buildApiArticle();

    $this->getJson('/api/v1/content/api-articles/999999')->assertStatus(404);
});

it('shows a single published entry with the full envelope', function () {
    [, $carClass] = buildApiArticle();
    $car = $carClass::create(['brand' => 'Peugeot', 'price' => 25000, 'internal_note' => '', 'slug' => 'peugeot', 'status' => 'published']);

    $this->getJson("/api/v1/content/api-articles/{$car->getRouteKey()}")
        ->assertOk()
        ->assertJsonPath('data.brand', 'Peugeot')
        ->assertJsonPath('data.slug', 'peugeot');
});

it('denies showing a draft entry without a session and allows it with permission', function () {
    [, $carClass] = buildApiArticle();
    $car = $carClass::create(['brand' => 'Peugeot', 'price' => 25000, 'internal_note' => '', 'slug' => 'peugeot', 'status' => 'draft']);

    $this->getJson("/api/v1/content/api-articles/{$car->getRouteKey()}")->assertStatus(401);

    $noPermission = apiActor([]);
    $this->actingAs($noPermission, 'baobab')
        ->getJson("/api/v1/content/api-articles/{$car->getRouteKey()}")
        ->assertStatus(403);

    $withPermission = apiActor(['content.api_article.view']);
    $this->actingAs($withPermission, 'baobab')
        ->getJson("/api/v1/content/api-articles/{$car->getRouteKey()}")
        ->assertOk();
});

it('filters entries on an exposed field with an operator', function () {
    [, $carClass] = buildApiArticle();
    $carClass::create(['brand' => 'Peugeot', 'price' => 10000, 'internal_note' => '', 'slug' => 'peugeot', 'status' => 'published']);
    $carClass::create(['brand' => 'Renault', 'price' => 20000, 'internal_note' => '', 'slug' => 'renault', 'status' => 'published']);

    $this->getJson('/api/v1/content/api-articles?filter[price][gte]=15000')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.brand', 'Renault');
});

it('rejects a filter on a field not marked exposed_in_api with a 422 problem+json response', function () {
    buildApiArticle();

    $this->getJson('/api/v1/content/api-articles?filter[internal_note]=secret')
        ->assertStatus(422)
        ->assertJsonPath('type', 'https://docs.baobabcms.com/errors/validation')
        ->assertJsonStructure(['type', 'title', 'status', 'errors']);
});

it('sorts entries on an exposed field', function () {
    [, $carClass] = buildApiArticle();
    $carClass::create(['brand' => 'Peugeot', 'price' => 10000, 'internal_note' => '', 'slug' => 'peugeot', 'status' => 'published']);
    $carClass::create(['brand' => 'Renault', 'price' => 20000, 'internal_note' => '', 'slug' => 'renault', 'status' => 'published']);

    $this->getJson('/api/v1/content/api-articles?sort=-price')
        ->assertOk()
        ->assertJsonPath('data.0.brand', 'Renault')
        ->assertJsonPath('data.1.brand', 'Peugeot');
});

it('restricts serialized keys with ?fields= without ever exposing a non-exposed field', function () {
    [, $carClass] = buildApiArticle();
    $carClass::create(['brand' => 'Peugeot', 'price' => 25000, 'internal_note' => '', 'slug' => 'peugeot', 'status' => 'published']);

    $data = $this->getJson('/api/v1/content/api-articles?fields=brand,internal_note')
        ->assertOk()
        ->json('data.0');

    expect($data)->toHaveKeys(['id', 'brand'])
        ->and($data)->not->toHaveKey('internal_note')
        ->and($data)->not->toHaveKey('price');
});

it('includes a declared relation and rejects an undeclared one', function () {
    // Clé distincte de 'ApiArticle' : le modèle Eloquent généré par un test
    // précédent est déjà chargé en mémoire PHP (autoloading, une fois par
    // process) — régénérer le module sur disque avec une relation en plus
    // sous la même clé ne change rien à la classe déjà définie.
    $manufacturerClass = buildApiManufacturer();
    [, $carClass] = buildApiArticle([
        'key' => 'RelatedVehicle',
        'relations' => [
            ['key' => 'manufacturer', 'type' => 'one_to_many', 'target' => 'ApiManufacturer'],
        ],
    ]);

    $manufacturer = $manufacturerClass::create(['name' => 'Stellantis']);
    $carClass::create([
        'brand' => 'Peugeot',
        'price' => 25000,
        'internal_note' => '',
        'slug' => 'peugeot',
        'status' => 'published',
        'manufacturer_id' => $manufacturer->getKey(),
    ]);

    $this->getJson('/api/v1/content/related-vehicles?include=manufacturer')
        ->assertOk()
        ->assertJsonPath('data.0.manufacturer.name', 'Stellantis');

    $this->getJson('/api/v1/content/related-vehicles?include=bogus')
        ->assertStatus(422)
        ->assertJsonPath('type', 'https://docs.baobabcms.com/errors/validation');
});

it('paginates page-based results and honours per_page', function () {
    [, $carClass] = buildApiArticle();

    foreach (range(1, 3) as $i) {
        $carClass::create(['brand' => "Car {$i}", 'price' => 10000, 'internal_note' => '', 'slug' => "car-{$i}", 'status' => 'published']);
    }

    $this->getJson('/api/v1/content/api-articles?per_page=2')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.pagination.total', 3)
        ->assertJsonPath('meta.pagination.per_page', 2);
});

it('paginates by cursor without a total/current_page in the envelope', function () {
    [, $carClass] = buildApiArticle();

    foreach (range(1, 3) as $i) {
        $carClass::create(['brand' => "Car {$i}", 'price' => 10000, 'internal_note' => '', 'slug' => "car-{$i}", 'status' => 'published']);
    }

    $response = $this->getJson('/api/v1/content/api-articles?cursor=&per_page=2')->assertOk();

    $response->assertJsonPath('meta.pagination.per_page', 2)
        ->assertJsonMissingPath('meta.pagination.total')
        ->assertJsonMissingPath('meta.pagination.current_page');
});

/**
 * La bascule du n° 166 : les six routes `{entry}` se résolvent sur
 * l'identifiant public et **plus du tout** sur la clé primaire entière. Garder
 * un repli sur l'entier laisserait le catalogue énumérable — c'est-à-dire
 * exactement le défaut que la règle corrige.
 */
it('resolves an addressable entry by its slug and no longer by its integer key', function () {
    [, $entryClass] = buildApiArticle();
    $entry = $entryClass::create([
        'brand' => 'Peugeot', 'price' => 25000, 'internal_note' => '',
        'slug' => 'peugeot', 'status' => 'published',
    ]);

    $this->getJson('/api/v1/content/api-articles/peugeot')
        ->assertOk()
        ->assertJsonPath('data.brand', 'Peugeot')
        // `data.id` doit pouvoir être rejoué sur la route : c'est la clé de
        // route qui sort, jamais l'entier.
        ->assertJsonPath('data.id', 'peugeot');

    $this->getJson("/api/v1/content/api-articles/{$entry->getKey()}")->assertNotFound();
});

it('resolves a non-addressable entry by its uuid and no longer by its integer key', function () {
    // Type construit ici plutôt que via buildApiArticle() : un type non
    // adressable ne déclare pas de `title_field`, et le helper ne peut que
    // remplacer une clé, pas la retirer.
    $contentType = app(BuildContentType::class)((string) json_encode([
        'key' => 'ApiLedgerLine',
        'label' => ['singular' => 'Écriture', 'plural' => 'Écritures'],
        'fields' => [['key' => 'label', 'type' => 'text', 'required' => true]],
    ]));

    app(ModuleAutoloader::class)->registerFor(Module::findOrFail($contentType->module_id));

    /** @var class-string<Model> $entryClass */
    $entryClass = $contentType->modelClass();

    $entry = $entryClass::create(['label' => 'Écriture 42', 'status' => 'published']);

    $uuid = $entry->fresh()->getRouteKey();

    // Le trait a rempli la colonne sans toucher à la clé primaire.
    expect($uuid)->toBeString()->toHaveLength(36)
        ->and($entry->getKey())->toBeInt();

    // Un type non adressable n'est jamais lisible publiquement (§7) : la
    // lecture passe par un acteur autorisé, ce qui n'ôte rien à ce que le test
    // vérifie — la clé par laquelle l'entrée se résout.
    $actor = apiActor(['content.api_ledger_line.view']);

    $this->actingAs($actor, 'baobab')
        ->getJson("/api/v1/content/api-ledger-lines/{$uuid}")
        ->assertOk()
        ->assertJsonPath('data.id', $uuid);

    $this->actingAs($actor, 'baobab')
        ->getJson("/api/v1/content/api-ledger-lines/{$entry->getKey()}")
        ->assertNotFound();
});
