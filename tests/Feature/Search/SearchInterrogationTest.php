<?php

use Baobab\Access\Actions\GrantPermission;
use Baobab\Facades\Hook;
use Baobab\Search\Actions\RunSearch;
use Baobab\Search\SearchResultItem;
use Baobab\Search\SearchResults;
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

/**
 * Même clé que SearchSourcesTest (forme de champs identique — obligatoire,
 * une classe déjà chargée ne peut pas être redéclarée, cf. suivi n° 78).
 */
function buildInterrogationCar(): array
{
    return buildApiArticle([
        'key' => 'SearchableArticle',
        'fields' => [['key' => 'brand', 'type' => 'text', 'required' => true, 'searchable' => true]],
    ]);
}

function interrogationAdmin(): User
{
    static $counter = 0;
    $counter++;

    $user = User::create([
        'name' => "Search actor {$counter}",
        'email' => "search-actor-{$counter}@example.com",
        'password' => 'secret',
    ]);

    app(GrantPermission::class)($user, 'baobab.admin.access');

    return $user;
}

// ── RunSearch ───────────────────────────────────────────────────────────────

it('groups results by source key and skips sources outside the requested context', function () {
    [, $carClass] = buildInterrogationCar();
    $carClass::create(['brand' => 'Renault Clio', 'slug' => 'clio', 'status' => 'published']);

    $groups = app(RunSearch::class)('Clio', 'front', null);

    expect($groups)->toHaveKey('core.contents')
        ->and($groups)->not->toHaveKey('core.users')
        ->and($groups['core.contents']['results']->items[0]->sourceKey)->toBe('core.contents');
});

it('applies the baobab.search.results filter per source', function () {
    [, $carClass] = buildInterrogationCar();
    $carClass::create(['brand' => 'Renault Clio', 'slug' => 'clio', 'status' => 'published']);

    Hook::modify('baobab.search.results', function (SearchResults $results, string $sourceKey) {
        return new SearchResults([new SearchResultItem(title: 'Filtered', url: '/filtered', sourceKey: $sourceKey)]);
    });

    $groups = app(RunSearch::class)('Clio', 'front', null);

    expect($groups['core.contents']['results']->items[0]->title)->toBe('Filtered');
});

// ── Omnibox admin ───────────────────────────────────────────────────────────

it('returns empty for a query under two characters', function () {
    $this->actingAs(interrogationAdmin(), 'baobab')
        ->getJson(route('admin.omnibox.search', ['q' => 'a']))
        ->assertOk()
        ->assertExactJson([]);
});

it('returns grouped results for an authorized actor', function () {
    [, $carClass] = buildInterrogationCar();
    $carClass::create(['brand' => 'Renault Clio', 'slug' => 'clio', 'status' => 'published']);

    $this->actingAs(interrogationAdmin(), 'baobab')
        ->getJson(route('admin.omnibox.search', ['q' => 'Clio']))
        ->assertOk()
        ->assertJsonPath('0.source', 'core.contents')
        ->assertJsonPath('0.items.0.title', 'Renault Clio');
});

it('keeps the omnibox reachable during an impersonation', function () {
    $admin = interrogationAdmin();

    $this->actingAs($admin, 'baobab')
        ->withSession(['baobab.impersonator_id' => 999])
        ->getJson(route('admin.omnibox.search', ['q' => 'anything']))
        ->assertOk();
});

// ── GET /api/v1/search ──────────────────────────────────────────────────────

it('serves published-only front results with the REST envelope', function () {
    [, $carClass] = buildInterrogationCar();
    $carClass::create(['brand' => 'Renault Clio', 'slug' => 'clio', 'status' => 'published']);
    $carClass::create(['brand' => 'Clio Draft', 'slug' => 'clio-draft', 'status' => 'draft']);

    $response = test()->getJson('/api/v1/search?q=Clio');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Renault Clio')
        ->assertJsonPath('data.0.source', 'core.contents')
        ->assertJsonPath('meta.pagination.total', 1);
});

it('restricts the search to one content type via ?type=', function () {
    [, $carClass] = buildInterrogationCar();
    $carClass::create(['brand' => 'Renault Clio', 'slug' => 'clio', 'status' => 'published']);

    test()->getJson('/api/v1/search?q=Clio&type=SearchableArticle')->assertOk()->assertJsonCount(1, 'data');
    test()->getJson('/api/v1/search?q=Clio&type=Nonexistent')->assertOk()->assertJsonCount(0, 'data');
});

it('rejects a non-filterable field with a 422', function () {
    [, $carClass] = buildInterrogationCar();
    $carClass::create(['brand' => 'Renault Clio', 'slug' => 'clio', 'status' => 'published']);

    test()->getJson('/api/v1/search?q=Clio&type=SearchableArticle&filter[secret_field]=x')
        ->assertStatus(422);
});

it('never exposes users or media on the front context, even for an authenticated admin', function () {
    User::create(['name' => 'Findable Front', 'email' => 'findable-front@example.com', 'password' => 'secret']);

    $admin = interrogationAdmin();
    app(GrantPermission::class)($admin, 'baobab.users.impersonate');

    $response = test()->actingAs($admin, 'baobab')->getJson('/api/v1/search?q=Findable');

    $response->assertOk()->assertJsonCount(0, 'data');
});

// ── Page publique /search ───────────────────────────────────────────────────

it('renders the public search page through the theme template hierarchy', function () {
    [, $carClass] = buildInterrogationCar();
    $carClass::create(['brand' => 'Renault Clio', 'slug' => 'clio', 'status' => 'published']);

    $response = test()->get('/search?q=Clio');

    $response->assertOk()
        ->assertViewIs('baobab::templates.search')
        ->assertSee('Renault Clio');
});

it('renders the public search page without results for an empty query', function () {
    test()->get('/search')->assertOk()->assertViewHas('results', []);
});

// ── Helper de thème ─────────────────────────────────────────────────────────

it('exposes baobab_search() returning front groups', function () {
    [, $carClass] = buildInterrogationCar();
    $carClass::create(['brand' => 'Renault Clio', 'slug' => 'clio', 'status' => 'published']);

    expect(function_exists('baobab_search'))->toBeTrue();

    $groups = baobab_search('Clio');

    expect($groups)->toHaveKey('core.contents')
        ->and($groups['core.contents']['results']->items[0]->url)->toContain('/searchable-articles/clio');
});

// ── Écran admin/search ──────────────────────────────────────────────────────

it('denies the search settings screen without baobab.system.search.manage', function () {
    $this->actingAs(interrogationAdmin(), 'baobab')
        ->get(route('admin.search.index'))
        ->assertForbidden();
});

it('shows driver, sources and index state to an authorized actor', function () {
    buildInterrogationCar();

    $admin = interrogationAdmin();
    app(GrantPermission::class)($admin, 'baobab.system.search.manage');

    $this->actingAs($admin, 'baobab')
        ->get(route('admin.search.index'))
        ->assertOk()
        ->assertSee('database')
        ->assertSee('core.contents')
        ->assertSee('SearchableArticle');
});

it('blocks the search settings screen during an impersonation', function () {
    $admin = interrogationAdmin();
    app(GrantPermission::class)($admin, 'baobab.system.search.manage');

    $this->actingAs($admin, 'baobab')
        ->withSession(['baobab.impersonator_id' => 999])
        ->get(route('admin.search.index'))
        ->assertForbidden();
});

it('reindexes from the admin screen', function () {
    buildInterrogationCar();

    $admin = interrogationAdmin();
    app(GrantPermission::class)($admin, 'baobab.system.search.manage');

    $this->actingAs($admin, 'baobab')
        ->post(route('admin.search.reindex'))
        ->assertRedirect(route('admin.search.index'));
});

// ── Hook baobab.search.indexing ─────────────────────────────────────────────

it('routes the generated toSearchableArray through the baobab.search.indexing filter', function () {
    [$contentType, $carClass] = buildInterrogationCar();

    $modelSource = File::get($contentType->moduleDir()."/src/Models/{$contentType->key}.php");

    expect($modelSource)->toContain("Hook::filter('baobab.search.indexing'");

    Hook::modify('baobab.search.indexing', function (array $document) {
        $document['injected'] = 'yes';

        return $document;
    });

    $entry = $carClass::create(['brand' => 'Hooked', 'slug' => 'hooked', 'status' => 'published']);

    expect($entry->toSearchableArray())->toHaveKey('injected', 'yes');
});
