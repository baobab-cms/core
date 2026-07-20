<?php

use Baobab\Api\GraphQL\Actions\CompileGraphqlSchema;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    File::deleteDirectory(generatedModulesPath());
    config(['baobab.content_types.modules_path' => generatedModulesPath()]);
    config(['baobab.modules.paths' => ['local' => [generatedModulesPath().'/*']]]);
});

afterEach(function () {
    File::deleteDirectory(generatedModulesPath());
});

it('lists a paginated, filtered and sorted GraphQL query mirroring REST semantics', function () {
    [, $carClass] = buildApiCar();
    $carClass::create(['brand' => 'Peugeot', 'price' => 10000, 'internal_note' => '', 'slug' => 'peugeot', 'status' => 'published']);
    $carClass::create(['brand' => 'Renault', 'price' => 20000, 'internal_note' => '', 'slug' => 'renault', 'status' => 'published']);

    $response = graphqlQuery(<<<'GRAPHQL'
        query {
          apiCars(filter: { price: { gte: 15000 } }, orderBy: [{ field: PRICE, direction: DESC }]) {
            data { id brand price }
            paginatorInfo { total }
          }
        }
        GRAPHQL);

    $response->assertOk()->assertJsonMissingPath('errors');
    expect($response->json('data.apiCars.data.0.brand'))->toBe('Renault')
        ->and($response->json('data.apiCars.paginatorInfo.total'))->toBe(1);
});

it('never exposes a field not marked exposed_in_api in the GraphQL schema', function () {
    buildApiCar();

    $response = graphqlQuery('query { apiCars { data { internalNote } } }');

    $response->assertOk()->assertJsonPath('errors.0.message', fn (string $message) => str_contains($message, 'internalNote'));
});

it('resolves a single entry by id through the find query', function () {
    [, $carClass] = buildApiCar();
    $car = $carClass::create(['brand' => 'Peugeot', 'price' => 25000, 'internal_note' => '', 'slug' => 'peugeot', 'status' => 'published']);

    $response = graphqlQuery('query($id: ID) { apiCar(id: $id) { brand slug } }', ['id' => $car->getKey()]);

    $response->assertOk()
        ->assertJsonPath('data.apiCar.brand', 'Peugeot')
        ->assertJsonPath('data.apiCar.slug', 'peugeot');
});

it('restricts a public list to published entries and errors without authentication for a draft-only request context', function () {
    [, $carClass] = buildApiCar();
    $carClass::create(['brand' => 'Peugeot', 'price' => 25000, 'internal_note' => '', 'slug' => 'peugeot', 'status' => 'published']);
    $carClass::create(['brand' => 'Renault', 'price' => 25000, 'internal_note' => '', 'slug' => 'renault', 'status' => 'draft']);

    $response = graphqlQuery('query { apiCars { data { brand } } }');

    $response->assertOk();
    expect($response->json('data.apiCars.data'))->toHaveCount(1);
});

it('denies a list entirely via a GraphQL authentication error when public_api_read is disabled', function () {
    buildApiCar(['public_api_read' => false]);

    $response = graphqlQuery('query { apiCars { data { brand } } }');

    $response->assertOk();
    expect($response->json('data.apiCars'))->toBeNull()
        ->and($response->json('errors.0.message'))->toBe('Unauthenticated.');
});

it('lets an authenticated actor with viewAny read a type with public_api_read disabled', function () {
    [, $carClass] = buildApiCar(['public_api_read' => false]);
    $carClass::create(['brand' => 'Peugeot', 'price' => 25000, 'internal_note' => '', 'slug' => 'peugeot', 'status' => 'published']);
    $actor = apiActor(['content.api_car.view']);

    $response = $this->actingAs($actor, 'baobab')
        ->postJson('/graphql', ['query' => 'query { apiCars { data { brand } } }']);

    $response->assertOk();
    expect($response->json('data.apiCars.data'))->toHaveCount(1);
});

it('drops a Content Type from the compiled schema when api_enabled is disabled', function () {
    buildApiCar(['api_enabled' => false]);

    $response = graphqlQuery('query { apiCars { data { brand } } }');

    $response->assertOk()->assertJsonPath('errors.0.message', fn (string $message) => str_contains($message, 'apiCars'));
});

it('self-heals a Content Type built before the GraphQL fragment/resolver existed', function () {
    [$contentType, $carClass] = buildApiCar();
    $carClass::create(['brand' => 'Peugeot', 'price' => 10000, 'internal_note' => '', 'slug' => 'peugeot', 'status' => 'published']);

    // Simule un Content Type construit avant M7 point 3 : ni fragment ni
    // résolveur sur disque, seul le module.json/modèle/policy existent.
    File::delete($contentType->moduleDir().'/graphql/ApiCar.graphql');
    File::deleteDirectory($contentType->moduleDir().'/src/GraphQL');

    // Recompiler (ex. `baobab:graphql:compile` sur un site existant après
    // mise à jour) doit régénérer le fragment/résolveur manquants avant de
    // les lire, pas simplement échouer à trouver `apiCars` dans le schéma.
    app(CompileGraphqlSchema::class)();

    expect(File::exists($contentType->moduleDir().'/graphql/ApiCar.graphql'))->toBeTrue()
        ->and(File::exists($contentType->moduleDir().'/src/GraphQL/ApiCarResolver.php'))->toBeTrue();

    $response = graphqlQuery('query { apiCars { data { brand } } }');

    $response->assertOk()->assertJsonMissingPath('errors');
    expect($response->json('data.apiCars.data.0.brand'))->toBe('Peugeot');
});
