<?php

use Baobab\ContentTypes\Editorial\Models\Revision;
use Baobab\ContentTypes\Support\ContentTrash;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    File::deleteDirectory(generatedModulesPath());
    config(['baobab.content_types.modules_path' => generatedModulesPath()]);
    config(['baobab.modules.paths' => ['local' => [generatedModulesPath().'/*']]]);
});

afterEach(function () {
    File::deleteDirectory(generatedModulesPath());
});

it('creates an entry and returns 201 with a Location header and the resource', function () {
    buildApiArticle();
    $actor = apiActor(['content.api_article.create']);

    $response = $this->actingAs($actor, 'baobab')
        ->postJson('/api/v1/content/api-articles', ['brand' => 'Peugeot', 'price' => 15000, 'internal_note' => 'n/a', 'slug' => 'peugeot'])
        ->assertCreated()
        ->assertJsonPath('data.brand', 'Peugeot');

    expect($response->headers->get('Location'))->toContain('/api/v1/content/api-articles/');
});

it('returns 401 creating without a session', function () {
    buildApiArticle();

    $this->postJson('/api/v1/content/api-articles', ['brand' => 'Peugeot', 'slug' => 'peugeot'])
        ->assertStatus(401);
});

it('returns 403 creating without content.{type}.create', function () {
    buildApiArticle();
    $actor = apiActor([]);

    $this->actingAs($actor, 'baobab')
        ->postJson('/api/v1/content/api-articles', ['brand' => 'Peugeot', 'slug' => 'peugeot'])
        ->assertStatus(403)
        ->assertJsonPath('type', 'https://docs.baobabcms.com/errors/forbidden');
});

it('returns 422 problem+json when a required field is missing on create', function () {
    buildApiArticle();
    $actor = apiActor(['content.api_article.create']);

    $this->actingAs($actor, 'baobab')
        ->postJson('/api/v1/content/api-articles', ['slug' => 'no-brand'])
        ->assertStatus(422)
        ->assertJsonPath('type', 'https://docs.baobabcms.com/errors/validation')
        ->assertJsonPath('errors.brand.0', fn ($value) => is_string($value));
});

it('disambiguates a conflicting slug on create instead of rejecting it', function () {
    buildApiArticle();
    $actor = apiActor(['content.api_article.create']);

    $this->actingAs($actor, 'baobab')
        ->postJson('/api/v1/content/api-articles', ['brand' => 'Peugeot', 'price' => 1000, 'internal_note' => 'n/a', 'slug' => 'peugeot'])
        ->assertCreated();

    $this->actingAs($actor, 'baobab')
        ->postJson('/api/v1/content/api-articles', ['brand' => 'Peugeot 2', 'price' => 2000, 'internal_note' => 'n/a', 'slug' => 'peugeot'])
        ->assertCreated()
        ->assertJsonPath('data.slug', 'peugeot-2');
});

it('updates only the fields provided on a partial PATCH', function () {
    [, $carClass] = buildApiArticle();
    $owner = apiActor(['content.api_article.update']);
    $entry = $carClass::create([
        'brand' => 'Peugeot', 'price' => 10000, 'internal_note' => 'n/a', 'slug' => 'peugeot',
        'status' => 'draft', 'author_id' => $owner->id,
    ]);

    $this->actingAs($owner, 'baobab')
        ->patchJson("/api/v1/content/api-articles/{$entry->getRouteKey()}", ['price' => 20000])
        ->assertOk()
        ->assertJsonPath('data.price', 20000)
        ->assertJsonPath('data.brand', 'Peugeot');
});

it('forbids updating another actor\'s entry without update_any', function () {
    [, $carClass] = buildApiArticle();
    $owner = apiActor(['content.api_article.update']);
    $entry = $carClass::create([
        'brand' => 'Peugeot', 'price' => 10000, 'internal_note' => 'n/a', 'slug' => 'peugeot',
        'status' => 'draft', 'author_id' => $owner->id,
    ]);

    $otherWithoutAny = apiActor(['content.api_article.update']);
    $this->actingAs($otherWithoutAny, 'baobab')
        ->patchJson("/api/v1/content/api-articles/{$entry->getRouteKey()}", ['price' => 1])
        ->assertStatus(403);

    $otherWithAny = apiActor(['content.api_article.update_any']);
    $this->actingAs($otherWithAny, 'baobab')
        ->patchJson("/api/v1/content/api-articles/{$entry->getRouteKey()}", ['price' => 1])
        ->assertOk();
});

it('returns 404 patching an unknown entry', function () {
    buildApiArticle();
    $actor = apiActor(['content.api_article.update_any']);

    $this->actingAs($actor, 'baobab')
        ->patchJson('/api/v1/content/api-articles/999999', ['price' => 1])
        ->assertStatus(404);
});

it('soft deletes on DELETE and the entry disappears from the public index', function () {
    [, $carClass] = buildApiArticle();
    $owner = apiActor(['content.api_article.delete']);
    $entry = $carClass::create([
        'brand' => 'Peugeot', 'price' => 10000, 'internal_note' => 'n/a', 'slug' => 'peugeot',
        'status' => 'published', 'author_id' => $owner->id,
    ]);

    $this->actingAs($owner, 'baobab')
        ->deleteJson("/api/v1/content/api-articles/{$entry->getRouteKey()}")
        ->assertStatus(204);

    $this->getJson('/api/v1/content/api-articles')->assertOk()->assertJsonCount(0, 'data');
});

it('requires baobab.trash.purge for a force delete regardless of the own delete permission', function () {
    [, $carClass] = buildApiArticle();
    $owner = apiActor(['content.api_article.delete']);
    $entry = $carClass::create([
        'brand' => 'Peugeot', 'price' => 10000, 'internal_note' => 'n/a', 'slug' => 'peugeot',
        'status' => 'published', 'author_id' => $owner->id,
    ]);

    $this->actingAs($owner, 'baobab')
        ->deleteJson("/api/v1/content/api-articles/{$entry->getRouteKey()}?force=true")
        ->assertStatus(403);

    $purger = apiActor(['baobab.trash.purge']);
    $this->actingAs($purger, 'baobab')
        ->deleteJson("/api/v1/content/api-articles/{$entry->getRouteKey()}?force=true")
        ->assertStatus(204);

    expect(ContentTrash::withTrashed($carClass)->find($entry->getKey()))->toBeNull();
});

it('publishes a draft entry and forbids it without the publish permission', function () {
    [, $carClass] = buildApiArticle();
    $owner = apiActor(['content.api_article.publish']);
    $entry = $carClass::create([
        'brand' => 'Peugeot', 'price' => 10000, 'internal_note' => 'n/a', 'slug' => 'peugeot',
        'status' => 'draft', 'author_id' => $owner->id,
    ]);

    $noPermission = apiActor([]);
    $this->actingAs($noPermission, 'baobab')
        ->postJson("/api/v1/content/api-articles/{$entry->getRouteKey()}/publish")
        ->assertStatus(403);

    $this->actingAs($owner, 'baobab')
        ->postJson("/api/v1/content/api-articles/{$entry->getRouteKey()}/publish")
        ->assertOk()
        ->assertJsonPath('data.status', 'published');
});

it('restores a trashed entry, reverting to draft when it was published, and 404s on a non-trashed entry', function () {
    [, $carClass] = buildApiArticle();
    $owner = apiActor(['content.api_article.delete']);
    $entry = $carClass::create([
        'brand' => 'Peugeot', 'price' => 10000, 'internal_note' => 'n/a', 'slug' => 'peugeot',
        'status' => 'published', 'author_id' => $owner->id,
    ]);

    $this->actingAs($owner, 'baobab')
        ->postJson("/api/v1/content/api-articles/{$entry->getRouteKey()}/restore")
        ->assertStatus(404);

    $entry->delete();

    $this->actingAs($owner, 'baobab')
        ->postJson("/api/v1/content/api-articles/{$entry->getRouteKey()}/restore")
        ->assertOk()
        ->assertJsonPath('data.status', 'draft');
});

it('lists only manual revisions after a series of patches', function () {
    [, $carClass] = buildApiArticle();
    $owner = apiActor(['content.api_article.update']);
    $entry = $carClass::create([
        'brand' => 'Peugeot', 'price' => 10000, 'internal_note' => 'n/a', 'slug' => 'peugeot',
        'status' => 'draft', 'author_id' => $owner->id,
    ]);

    $this->actingAs($owner, 'baobab')
        ->patchJson("/api/v1/content/api-articles/{$entry->getRouteKey()}", ['price' => 20000])
        ->assertOk();
    $this->actingAs($owner, 'baobab')
        ->patchJson("/api/v1/content/api-articles/{$entry->getRouteKey()}", ['price' => 30000])
        ->assertOk();

    Revision::create([
        'revisionable_type' => $entry->getMorphClass(),
        'revisionable_id' => $entry->getKey(),
        'type' => 'autosave',
        'snapshot' => ['price' => 99999],
        'author_id' => $owner->id,
    ]);

    $response = $this->actingAs($owner, 'baobab')
        ->getJson("/api/v1/content/api-articles/{$entry->getRouteKey()}/revisions")
        ->assertOk();

    $types = collect($response->json('data'))->pluck('type');

    expect($types)->each(fn ($type) => $type->toBe('manual'))
        ->and($types)->not->toContain('autosave');
});
