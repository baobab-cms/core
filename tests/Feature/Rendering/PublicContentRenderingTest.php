<?php

use Baobab\ContentTypes\Actions\BuildContentType;
use Baobab\Facades\Hook;
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

/**
 * Clé dédiée (pas la « Car » partagée par ~30 autres fichiers de test via
 * carBlueprintJson()) : la classe Eloquent générée est déclarée une seule
 * fois par processus PHP — en exécution séquentielle (hors --parallel), un
 * autre fichier ayant déjà bâti « Car » avec un blueprint différent (sans
 * is_addressable/slug) laisserait ce test écrire sur une classe figée sans
 * `slug` dans `$fillable`.
 *
 * @param  array<string, mixed>  $overrides
 */
function buildAddressableCar(array $overrides = []): array
{
    $contentType = app(BuildContentType::class)(carBlueprintJson(array_replace([
        'key' => 'RenderCar',
        'is_addressable' => true,
        'title_field' => 'brand',
        'fields' => [['key' => 'brand', 'type' => 'text', 'required' => true]],
    ], $overrides)));

    $module = Module::findOrFail($contentType->module_id);
    app(ModuleAutoloader::class)->registerFor($module);

    /** @var class-string<Model> $modelClass */
    $modelClass = $contentType->modelClass();

    return [$contentType->fresh(), $modelClass];
}

it('renders a published entry at /{prefix}/{slug}', function () {
    [$type, $modelClass] = buildAddressableCar();
    $modelClass::create(['brand' => 'Peugeot 208', 'slug' => 'peugeot-208', 'status' => 'published']);

    $response = $this->get('/render-cars/peugeot-208');

    $response->assertOk()->assertSee('Peugeot 208');
});

it('returns 404 for an unknown slug', function () {
    buildAddressableCar();

    $response = $this->get('/render-cars/does-not-exist');

    $response->assertNotFound();
});

it('returns 404 for a slug that exists but is not published', function () {
    [$type, $modelClass] = buildAddressableCar();
    $modelClass::create(['brand' => 'Draft Car', 'slug' => 'draft-car', 'status' => 'draft']);

    $response = $this->get('/render-cars/draft-car');

    $response->assertNotFound();
});

it('lists published entries on the archive route', function () {
    [$type, $modelClass] = buildAddressableCar();
    $modelClass::create(['brand' => 'Peugeot 208', 'slug' => 'peugeot-208', 'status' => 'published']);
    $modelClass::create(['brand' => 'Draft Car', 'slug' => 'draft-car', 'status' => 'draft']);

    $response = $this->get('/render-cars');

    $response->assertOk()->assertSee('peugeot-208')->assertDontSee('draft-car');
});

it('renders the Core fallback 404 template for a totally unrelated public URL', function () {
    buildAddressableCar();

    $response = $this->get('/this-route-does-not-exist-anywhere');

    $response->assertNotFound();
});

it('keeps an unmatched /admin/* URL on the ordinary Laravel 404, not the theme fallback', function () {
    buildAddressableCar();

    $response = $this->get('/admin/this-does-not-exist');

    $response->assertNotFound();
});

it('honors a url_prefix override from the blueprint', function () {
    [$type, $modelClass] = buildAddressableCar(['url_prefix' => 'voitures']);
    $modelClass::create(['brand' => 'Peugeot 208', 'slug' => 'peugeot-208', 'status' => 'published']);

    $this->get('/voitures/peugeot-208')->assertOk()->assertSee('Peugeot 208');
    $this->get('/render-cars/peugeot-208')->assertNotFound();
});

it('cancels the render and falls back to 404 when baobab.render.data returns null', function () {
    [$type, $modelClass] = buildAddressableCar();
    $modelClass::create(['brand' => 'Peugeot 208', 'slug' => 'peugeot-208', 'status' => 'published']);

    Hook::modify('baobab.render.data', fn () => null, priority: 5);

    $this->get('/render-cars/peugeot-208')->assertNotFound();
});

it('lets a module transform the final HTML via baobab.content.render', function () {
    [$type, $modelClass] = buildAddressableCar();
    $modelClass::create(['brand' => 'Peugeot 208', 'slug' => 'peugeot-208', 'status' => 'published']);

    Hook::modify('baobab.content.render', fn (string $html) => $html.'<!-- marker -->', priority: 5);

    $this->get('/render-cars/peugeot-208')->assertOk()->assertSee('<!-- marker -->', false);
});
