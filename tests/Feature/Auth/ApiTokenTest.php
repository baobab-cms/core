<?php

use Baobab\Access\Actions\RevokePermission;
use Baobab\Audit\Models\AuditEntry;
use Baobab\Auth\Actions\CreateApiToken;
use Baobab\Auth\Exceptions\InvalidTokenAbilityException;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    File::deleteDirectory(generatedModulesPath());
    config(['baobab.content_types.modules_path' => generatedModulesPath()]);
    config(['baobab.modules.paths' => ['local' => [generatedModulesPath().'/*']]]);
});

afterEach(function () {
    File::deleteDirectory(generatedModulesPath());
});

it('lets a Bearer token with the right ability perform a REST write', function () {
    buildApiCar();
    $actor = apiActor(['content.api_car.create']);

    $token = app(CreateApiToken::class)($actor, 'ci', ['content.api_car.create']);

    $this->withHeader('Authorization', "Bearer {$token->plainTextToken}")
        ->postJson('/api/v1/content/api-cars', ['brand' => 'Peugeot', 'price' => 1000, 'internal_note' => 'n/a', 'slug' => 'peugeot'])
        ->assertCreated();
});

it('denies a Bearer token missing the required ability even though the user holds the permission', function () {
    [, $carClass] = buildApiCar();
    $actor = apiActor(['content.api_car.create', 'content.api_car.publish']);
    $entry = $carClass::create([
        'brand' => 'Peugeot', 'price' => 1000, 'internal_note' => 'n/a', 'slug' => 'peugeot',
        'status' => 'draft', 'author_id' => $actor->id,
    ]);

    $token = app(CreateApiToken::class)($actor, 'ci', ['content.api_car.create']);

    $this->withHeader('Authorization', "Bearer {$token->plainTextToken}")
        ->postJson("/api/v1/content/api-cars/{$entry->getKey()}/publish")
        ->assertStatus(403);
});

it('denies a Bearer token once the creator loses the underlying permission', function () {
    buildApiCar();
    $actor = apiActor(['content.api_car.create']);

    $token = app(CreateApiToken::class)($actor, 'ci', ['content.api_car.create']);

    app(RevokePermission::class)($actor, 'content.api_car.create');

    $this->withHeader('Authorization', "Bearer {$token->plainTextToken}")
        ->postJson('/api/v1/content/api-cars', ['brand' => 'Peugeot', 'price' => 1000, 'internal_note' => 'n/a', 'slug' => 'peugeot'])
        ->assertStatus(403);
});

it('rejects a Bearer token without any Authorization header the same as before (401)', function () {
    buildApiCar();

    $this->postJson('/api/v1/content/api-cars', ['brand' => 'Peugeot', 'slug' => 'peugeot'])
        ->assertStatus(401);
});

it('CreateApiToken rejects an ability outside the actor\'s current permissions', function () {
    $actor = apiActor(['content.api_car.create']);

    expect(fn () => app(CreateApiToken::class)($actor, 'ci', ['content.api_car.publish']))
        ->toThrow(InvalidTokenAbilityException::class);
});

it('records the token identity on an audited API write', function () {
    buildApiCar();
    $actor = apiActor(['content.api_car.create']);

    $token = app(CreateApiToken::class)($actor, 'front headless', ['content.api_car.create']);

    $this->withHeader('Authorization', "Bearer {$token->plainTextToken}")
        ->postJson('/api/v1/content/api-cars', ['brand' => 'Peugeot', 'price' => 1000, 'internal_note' => 'n/a', 'slug' => 'peugeot'])
        ->assertCreated();

    $entry = AuditEntry::where('action', 'content.created')->latest('id')->first();

    expect($entry)->not->toBeNull()
        ->and($entry->actor_id)->toBe($actor->id)
        ->and($entry->data['token']['name'] ?? null)->toBe('front headless');
});

it('creates, lists and revokes a token from the self-service screen, only offering the actor\'s own permissions', function () {
    $actor = apiActor(['baobab.admin.access', 'content.api_car.create']);

    $this->actingAs($actor, 'baobab')
        ->get(route('admin.account.api-tokens.index'))
        ->assertOk()
        ->assertSee('content.api_car.create')
        ->assertDontSee('content.api_car.publish');

    $this->actingAs($actor, 'baobab')
        ->post(route('admin.account.api-tokens.store'), [
            'name' => 'my token',
            'abilities' => ['content.api_car.create'],
        ])
        ->assertRedirect(route('admin.account.api-tokens.index'));

    $token = $actor->tokens()->where('name', 'my token')->firstOrFail();

    $this->actingAs($actor, 'baobab')
        ->get(route('admin.account.api-tokens.index'))
        ->assertOk()
        ->assertSee('my token');

    $this->actingAs($actor, 'baobab')
        ->delete(route('admin.account.api-tokens.destroy', ['token' => $token->id]))
        ->assertRedirect(route('admin.account.api-tokens.index'));

    expect($actor->tokens()->find($token->id))->toBeNull();
});

it('rejects creating a token with an ability the actor does not hold via the self-service form', function () {
    $actor = apiActor(['baobab.admin.access', 'content.api_car.create']);

    $this->actingAs($actor, 'baobab')
        ->post(route('admin.account.api-tokens.store'), [
            'name' => 'my token',
            'abilities' => ['content.api_car.publish'],
        ])
        ->assertSessionHasErrors('abilities');
});
