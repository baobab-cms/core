<?php

use Baobab\Access\Actions\GrantPermission;
use Baobab\Api\Models\ApiSetting;
use Baobab\Users\Models\User;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    File::deleteDirectory(generatedModulesPath());
    config(['baobab.content_types.modules_path' => generatedModulesPath()]);
    config(['baobab.modules.paths' => ['local' => [generatedModulesPath().'/*']]]);
});

afterEach(function () {
    File::deleteDirectory(generatedModulesPath());
});

it('serves GraphQL normally while graphql_enabled is true (the default)', function () {
    buildApiCar();

    graphqlQuery('query { __typename }')->assertOk()->assertJsonMissingPath('errors');
});

it('responds 404 to GraphQL once graphql_enabled is turned off, independently of rest_enabled', function () {
    ApiSetting::current()->fill(['graphql_enabled' => false])->save();

    graphqlQuery('query { __typename }')
        ->assertStatus(404)
        ->assertJsonPath('type', 'https://docs.baobabcms.com/errors/not-found');
});

it('keeps GraphQL available when only rest_enabled is turned off', function () {
    buildApiCar();

    ApiSetting::current()->fill(['rest_enabled' => false])->save();

    graphqlQuery('query { __typename }')->assertOk()->assertJsonMissingPath('errors');
});

it('keeps REST available when only graphql_enabled is turned off', function () {
    [, $carClass] = buildApiCar();
    $carClass::create(['brand' => 'Peugeot', 'price' => 25000, 'internal_note' => '', 'slug' => 'peugeot', 'status' => 'published']);

    ApiSetting::current()->fill(['graphql_enabled' => false])->save();

    test()->getJson('/api/v1/content/api-cars')->assertOk();
});

it('sets CORS headers on GraphQL responses only when the request Origin is in the configured allow-list', function () {
    ApiSetting::current()->fill(['allowed_origins' => "https://front.example.com\n"])->save();

    test()->withHeader('Origin', 'https://front.example.com')
        ->postJson('/graphql', ['query' => 'query { __typename }'])
        ->assertOk()
        ->assertHeader('Access-Control-Allow-Origin', 'https://front.example.com');

    test()->withHeader('Origin', 'https://evil.example.com')
        ->postJson('/graphql', ['query' => 'query { __typename }'])
        ->assertOk()
        ->assertHeaderMissing('Access-Control-Allow-Origin');
});

it('applies the shared baobab-api rate limiter to GraphQL requests too', function () {
    ApiSetting::current()->fill(['rate_limit_per_minute' => 1])->save();

    graphqlQuery('query { __typename }')->assertOk();

    graphqlQuery('query { __typename }')
        ->assertStatus(429)
        ->assertJsonPath('type', 'https://docs.baobabcms.com/errors/rate-limited');
});

it('allows introspection by default outside of production', function () {
    graphqlQuery('query { __schema { types { name } } }')
        ->assertOk()
        ->assertJsonMissingPath('errors');
});

it('rejects introspection once graphql_introspection_enabled is disabled', function () {
    ApiSetting::current()->fill(['graphql_introspection_enabled' => false])->save();

    $response = graphqlQuery('query { __schema { types { name } } }');

    $response->assertOk();
    expect($response->json('errors.0.message'))->toContain('introspection');
});

it('rejects a GraphQL query exceeding the configured max depth of 10', function () {
    $nested = str_repeat('fields { type { ', 6).'name'.str_repeat(' } }', 6);

    $response = graphqlQuery("query { __schema { types { {$nested} } } }");

    $response->assertOk();
    expect($response->json('errors.0.message'))->toContain('Max query depth');
});

it('denies access to the API settings screen without baobab.system.api.manage', function () {
    $user = User::create(['name' => 'No access', 'email' => 'no-graphql-access@example.com', 'password' => 'secret']);
    app(GrantPermission::class)($user, 'baobab.admin.access');

    test()->actingAs($user, 'baobab')
        ->get(route('admin.api.index'))
        ->assertForbidden();
});

it('shows and persists GraphQL settings updates on the API settings screen', function () {
    $user = User::create(['name' => 'API admin', 'email' => 'graphql-admin@example.com', 'password' => 'secret']);
    app(GrantPermission::class)($user, 'baobab.admin.access');
    app(GrantPermission::class)($user, 'baobab.system.api.manage');

    test()->actingAs($user, 'baobab')
        ->post(route('admin.api.update'), [
            'rest_enabled' => '1',
            'rate_limit_per_minute' => 60,
            'graphql_introspection_enabled' => '1',
        ])
        ->assertRedirect(route('admin.api.index'));

    $setting = ApiSetting::current();

    expect($setting->graphql_enabled)->toBeFalse()
        ->and($setting->graphql_introspection_enabled)->toBeTrue();
});
