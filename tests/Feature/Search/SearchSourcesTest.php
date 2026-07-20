<?php

use Baobab\Access\Actions\GrantPermission;
use Baobab\Search\Exceptions\UnknownSearchSourceException;
use Baobab\Search\SearchRegistry;
use Baobab\Search\Sources\ContentsSearchSource;
use Baobab\Search\Sources\MediaSearchSource;
use Baobab\Search\Sources\UsersSearchSource;
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
 * Clé distincte d'ApiCar (utilisée par de nombreux autres tests
 * REST/GraphQL/OpenAPI sans champ `searchable`) : une classe PHP déjà
 * chargée en mémoire dans ce process ne peut pas être redéclarée avec un
 * fillable/trait différent, même après régénération du fichier sur disque.
 */
function buildSearchableCar(): array
{
    return buildApiCar([
        'key' => 'SearchableCar',
        'fields' => [['key' => 'brand', 'type' => 'text', 'required' => true, 'searchable' => true]],
    ]);
}

it('has the three Core sources registered at boot', function () {
    $registry = app(SearchRegistry::class);

    expect($registry->has('core.contents'))->toBeTrue()
        ->and($registry->has('core.users'))->toBeTrue()
        ->and($registry->has('core.media'))->toBeTrue();
});

it('throws for an unknown source key', function () {
    expect(fn () => app(SearchRegistry::class)->resolve('nope'))->toThrow(UnknownSearchSourceException::class);
});

it('ContentsSearchSource returns a published entry of a public addressable type without an actor', function () {
    [, $carClass] = buildSearchableCar();
    $carClass::create(['brand' => 'Renault Clio', 'slug' => 'clio', 'status' => 'published']);

    $results = app(ContentsSearchSource::class)->query('Clio', null);

    expect($results->items)->toHaveCount(1)
        ->and($results->items[0]->title)->toBe('Renault Clio');
});

it('ContentsSearchSource hides a non-published entry from an unauthenticated search', function () {
    [, $carClass] = buildSearchableCar();
    $carClass::create(['brand' => 'Draft Clio', 'slug' => 'draft-clio', 'status' => 'draft']);

    $results = app(ContentsSearchSource::class)->query('Clio', null);

    expect($results->items)->toBe([]);
});

it('ContentsSearchSource shows a non-published entry to an actor with viewAny', function () {
    [, $carClass] = buildSearchableCar();
    $carClass::create(['brand' => 'Draft Clio', 'slug' => 'draft-clio', 'status' => 'draft']);

    $actor = User::create(['name' => 'Editor', 'email' => 'search-editor@example.com', 'password' => 'secret']);
    app(GrantPermission::class)($actor, 'content.searchable_car.view');

    $results = app(ContentsSearchSource::class)->query('Clio', $actor);

    expect($results->items)->toHaveCount(1);
});

it('UsersSearchSource returns nothing without the impersonation permission', function () {
    User::create(['name' => 'Findable User', 'email' => 'findable@example.com', 'password' => 'secret']);
    $actor = User::create(['name' => 'No Access', 'email' => 'no-access@example.com', 'password' => 'secret']);

    $results = app(UsersSearchSource::class)->query('Findable', $actor);

    expect($results->items)->toBe([]);
});

it('UsersSearchSource matches by name or email for an actor with the impersonation permission', function () {
    User::create(['name' => 'Findable User', 'email' => 'findable@example.com', 'password' => 'secret']);
    $actor = User::create(['name' => 'Admin', 'email' => 'admin-search@example.com', 'password' => 'secret']);
    app(GrantPermission::class)($actor, 'baobab.users.impersonate');

    $results = app(UsersSearchSource::class)->query('Findable', $actor);

    expect($results->items)->toHaveCount(1)
        ->and($results->items[0]->excerpt)->toBe('findable@example.com');
});

it('MediaSearchSource returns nothing without the viewAny permission', function () {
    $actor = User::create(['name' => 'No Access', 'email' => 'media-no-access@example.com', 'password' => 'secret']);

    $results = app(MediaSearchSource::class)->query('anything', $actor);

    expect($results->items)->toBe([]);
});
