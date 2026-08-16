<?php

use Baobab\ContentTypes\Actions\BuildContentType;
use Baobab\ContentTypes\Actions\SaveContentEntry;
use Baobab\ContentTypes\Models\ContentType;
use Baobab\Modules\Models\Module;
use Baobab\Modules\ModuleAutoloader;
use Baobab\Seo\Models\Redirect;
use Baobab\Users\Models\User;
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

/**
 * @return array{0: ContentType, 1: class-string<Model>}
 */
function buildSlugRedirectCar(): array
{
    $contentType = app(BuildContentType::class)(contentTypeBlueprintJson('RedirectedPage', [
        'is_addressable' => true,
        'title_field' => 'brand',
        'fields' => [['key' => 'brand', 'type' => 'text', 'required' => true]],
    ]));

    $module = Module::findOrFail($contentType->module_id);
    app(ModuleAutoloader::class)->registerFor($module);

    /** @var class-string<Model> $modelClass */
    $modelClass = $contentType->modelClass();

    return [$contentType->fresh(), $modelClass];
}

function slugRedirectActor(): User
{
    return User::create(['name' => 'Actor', 'email' => 'slug-redirect-actor-'.uniqid().'@example.com', 'password' => 'secret']);
}

it('creates an automatic 301 redirect when a published entry\'s slug changes', function () {
    [$type, $modelClass] = buildSlugRedirectCar();
    $actor = slugRedirectActor();
    $entry = $modelClass::create(['brand' => 'Peugeot 208', 'slug' => 'peugeot-208', 'status' => 'published']);

    app(SaveContentEntry::class)($type, ['slug' => 'peugeot-208-new'], $actor, $entry);

    $redirect = Redirect::where('source', '/redirected-pages/peugeot-208')->first();

    expect($redirect)->not->toBeNull()
        ->and($redirect->target)->toBe('/redirected-pages/peugeot-208-new')
        ->and($redirect->status_code)->toBe(301)
        ->and($redirect->source_kind)->toBe('auto');

    $this->get('/redirected-pages/peugeot-208')
        ->assertStatus(301)
        ->assertHeader('Location', url('/redirected-pages/peugeot-208-new'));
});

it('removes the stale forward redirect when a slug is renamed back to a previous value, avoiding a loop', function () {
    [$type, $modelClass] = buildSlugRedirectCar();
    $actor = slugRedirectActor();
    $entry = $modelClass::create(['brand' => 'Peugeot 208', 'slug' => 'a', 'status' => 'published']);

    app(SaveContentEntry::class)($type, ['slug' => 'b'], $actor, $entry);
    expect(Redirect::where('source', '/redirected-pages/a')->first()?->target)->toBe('/redirected-pages/b');

    // Renaming back to "a": without cleanup, both /a -> /b and /b -> /a
    // would exist at once (a two-hop loop for anything that follows
    // redirects). The stale /a -> /b entry is removed instead, since "a"
    // is live again and no longer needs a redirect away from it.
    app(SaveContentEntry::class)($type, ['slug' => 'a'], $actor, $entry->fresh());

    expect(Redirect::where('source', '/redirected-pages/a')->exists())->toBeFalse()
        ->and(Redirect::where('source', '/redirected-pages/b')->first()?->target)->toBe('/redirected-pages/a')
        ->and(Redirect::query()->count())->toBe(1);
});

it('never removes a manually created redirect even if a slug rename would otherwise consider it stale', function () {
    [$type, $modelClass] = buildSlugRedirectCar();
    $actor = slugRedirectActor();
    Redirect::create(['source' => '/redirected-pages/b', 'target' => '/kept-on-purpose', 'status_code' => 301, 'source_kind' => 'manual']);
    $entry = $modelClass::create(['brand' => 'Peugeot 208', 'slug' => 'a', 'status' => 'published']);

    app(SaveContentEntry::class)($type, ['slug' => 'b'], $actor, $entry);

    expect(Redirect::where('source', '/redirected-pages/b')->first()?->target)->toBe('/kept-on-purpose');
});

it('does not create a redirect when creating a new entry', function () {
    [$type] = buildSlugRedirectCar();
    $actor = slugRedirectActor();

    app(SaveContentEntry::class)($type, ['brand' => 'Peugeot 208', 'slug' => 'peugeot-208', 'status' => 'published'], $actor);

    expect(Redirect::query()->count())->toBe(0);
});

it('does not create a redirect when the slug is unchanged', function () {
    [$type, $modelClass] = buildSlugRedirectCar();
    $actor = slugRedirectActor();
    $entry = $modelClass::create(['brand' => 'Peugeot 208', 'slug' => 'peugeot-208', 'status' => 'published']);

    app(SaveContentEntry::class)($type, ['brand' => 'Peugeot 208 GTI'], $actor, $entry);

    expect(Redirect::query()->count())->toBe(0);
});
