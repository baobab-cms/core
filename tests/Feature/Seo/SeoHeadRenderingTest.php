<?php

use Baobab\ContentTypes\Actions\BuildContentType;
use Baobab\ContentTypes\Models\ContentType;
use Baobab\Modules\Models\Module;
use Baobab\Modules\ModuleAutoloader;
use Baobab\Rendering\Models\ReadingSetting;
use Baobab\Seo\Models\SeoMeta;
use Baobab\Seo\Models\SeoSetting;
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
 * @param  array<string, mixed>  $overrides
 * @return array{0: ContentType, 1: class-string<Model>}
 */
function buildSeoHeadCar(array $overrides = []): array
{
    $contentType = app(BuildContentType::class)(contentTypeBlueprintJson('SeoHeadArticle', array_replace([
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

it('renders resolved SEO tags in the <head> of a public entry page', function () {
    [, $modelClass] = buildSeoHeadCar();
    $entry = $modelClass::create(['brand' => 'Peugeot 208', 'slug' => 'peugeot-208', 'status' => 'published']);
    SeoMeta::forEntry($entry)->fill([
        'meta_title' => 'Peugeot 208 — Essai complet',
        'meta_description' => 'Tout savoir sur la Peugeot 208.',
    ])->save();

    $response = $this->get('/seo-head-articles/peugeot-208');

    $response->assertOk()
        ->assertSee('<title>Peugeot 208 — Essai complet</title>', false)
        ->assertSee('<meta name="description" content="Tout savoir sur la Peugeot 208.">', false)
        ->assertSee('<meta property="og:type" content="article">', false);
});

it('renders a noindex robots meta tag when the entry is flagged noindex', function () {
    [, $modelClass] = buildSeoHeadCar();
    $entry = $modelClass::create(['brand' => 'Peugeot 208', 'slug' => 'peugeot-208', 'status' => 'published']);
    SeoMeta::forEntry($entry)->fill(['robots_noindex' => true])->save();

    $this->get('/seo-head-articles/peugeot-208')
        ->assertSee('<meta name="robots" content="noindex,follow">', false);
});

it('omits the robots meta tag entirely when no directive is set', function () {
    // Hors production, ComposeSeoMeta force noindex par défaut (spec 07 §8,
    // Pass C) — ce test vérifie le cas "rien n'est configuré", qui n'existe
    // qu'en production.
    $this->app['env'] = 'production';

    [, $modelClass] = buildSeoHeadCar();
    $modelClass::create(['brand' => 'Peugeot 208', 'slug' => 'peugeot-208', 'status' => 'published']);

    $this->get('/seo-head-articles/peugeot-208')->assertDontSee('name="robots"', false);
});

it('renders website og:type on the archive page', function () {
    [, $modelClass] = buildSeoHeadCar();
    $modelClass::create(['brand' => 'Peugeot 208', 'slug' => 'peugeot-208', 'status' => 'published']);

    $this->get('/seo-head-cars')->assertSee('<meta property="og:type" content="website">', false);
});

it('renders the global site name on the homepage when no reading setting is configured', function () {
    SeoSetting::current()->fill(['site_name' => 'Acme Motors'])->save();
    ReadingSetting::current()->fill(['mode' => null])->save();

    $this->get('/')->assertSee('<title>Acme Motors</title>', false);
});
