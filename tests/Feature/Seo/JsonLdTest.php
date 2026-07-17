<?php

use Baobab\Branding\Models\BrandingSetting;
use Baobab\ContentTypes\Actions\BuildContentType;
use Baobab\ContentTypes\Models\ContentType;
use Baobab\Facades\Hook;
use Baobab\Media\Models\Media;
use Baobab\Modules\Models\Module;
use Baobab\Modules\ModuleAutoloader;
use Baobab\Rendering\Models\ReadingSetting;
use Baobab\Seo\Actions\ComposeJsonLd;
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
function buildJsonLdCar(array $overrides = []): array
{
    $contentType = app(BuildContentType::class)(carBlueprintJson(array_replace([
        'key' => 'JsonLdCar',
        'is_addressable' => true,
        'title_field' => 'brand',
        'fields' => [
            ['key' => 'brand', 'type' => 'text', 'required' => true],
            ['key' => 'price', 'type' => 'decimal'],
            ['key' => 'photo', 'type' => 'image'],
        ],
    ], $overrides)));

    $module = Module::findOrFail($contentType->module_id);
    app(ModuleAutoloader::class)->registerFor($module);

    /** @var class-string<Model> $modelClass */
    $modelClass = $contentType->modelClass();

    return [$contentType->fresh(), $modelClass];
}

function jsonLdTestMedia(): Media
{
    return Media::create([
        'disk' => 'public',
        'path' => 'media/2026/07/car.jpg',
        'file_name' => 'car.jpg',
        'mime_type' => 'image/jpeg',
        'size' => 1000,
        'checksum' => hash('sha256', 'jsonld-test-'.uniqid()),
    ]);
}

it('composes Organization and WebSite nodes on every page', function () {
    SeoSetting::current()->fill(['site_name' => 'Acme Motors'])->save();

    $graph = app(ComposeJsonLd::class)(null, null);

    $types = collect($graph)->pluck('@type');

    expect($types)->toContain('Organization')
        ->and($types)->toContain('WebSite');

    $organization = collect($graph)->firstWhere('@type', 'Organization');
    $website = collect($graph)->firstWhere('@type', 'WebSite');

    expect($organization['name'])->toBe('Acme Motors')
        ->and($organization['url'])->toBe(url('/'))
        ->and($website['name'])->toBe('Acme Motors')
        ->and($website)->not->toHaveKey('potentialAction');
});

it('includes the branding logo and sameAs profiles on the Organization node', function () {
    $logo = jsonLdTestMedia();
    BrandingSetting::current()->fill(['logo_media_id' => $logo->id])->save();
    SeoSetting::current()->fill([
        'organization_type' => 'Person',
        'social_profiles' => "https://facebook.com/acme\n\nhttps://twitter.com/acme\n",
    ])->save();

    $organization = collect(app(ComposeJsonLd::class)(null, null))->firstWhere('@type', 'Person');

    expect($organization['logo'])->toBe($logo->url())
        ->and($organization['sameAs'])->toBe(['https://facebook.com/acme', 'https://twitter.com/acme']);
});

it('omits sameAs and logo entirely when nothing is configured', function () {
    $organization = collect(app(ComposeJsonLd::class)(null, null))->firstWhere('@type', 'Organization');

    expect($organization)->not->toHaveKey('logo')
        ->and($organization)->not->toHaveKey('sameAs');
});

it('builds a 3-level BreadcrumbList (home, archive, entry) for an entry page', function () {
    [$type, $modelClass] = buildJsonLdCar();
    $entry = $modelClass::create(['brand' => 'Peugeot 208', 'slug' => 'peugeot-208', 'status' => 'published', 'price' => 0]);

    $graph = app(ComposeJsonLd::class)($type, $entry);
    $breadcrumb = collect($graph)->firstWhere('@type', 'BreadcrumbList');

    expect($breadcrumb)->not->toBeNull();

    $items = $breadcrumb['itemListElement'];

    expect($items)->toHaveCount(3)
        ->and($items[0]['item'])->toBe(url('/'))
        ->and($items[1]['item'])->toBe(url('/json-ld-cars'))
        ->and($items[2]['item'])->toBe(url('/json-ld-cars/peugeot-208'))
        ->and($items[2]['name'])->toBe('Peugeot 208')
        ->and($items[0]['position'])->toBe(1)
        ->and($items[2]['position'])->toBe(3);
});

it('omits BreadcrumbList on an archive page and on the site-wide (no content type) case', function () {
    [$type] = buildJsonLdCar();

    $archiveGraph = app(ComposeJsonLd::class)($type, null);
    $siteGraph = app(ComposeJsonLd::class)(null, null);

    expect(collect($archiveGraph)->firstWhere('@type', 'BreadcrumbList'))->toBeNull()
        ->and(collect($siteGraph)->firstWhere('@type', 'BreadcrumbList'))->toBeNull();
});

it('builds a schema.org node from the blueprint mapping, resolving {title}, an image field, and a nested object', function () {
    [$type, $modelClass] = buildJsonLdCar([
        'seo' => [
            'schema' => [
                'type' => 'Product',
                'properties' => [
                    'name' => '{title}',
                    'image' => '{photo}',
                    'offers' => ['price' => '{price}', 'priceCurrency' => 'XOF'],
                ],
            ],
        ],
    ]);

    $media = jsonLdTestMedia();
    $entry = $modelClass::create([
        'brand' => 'Peugeot 208',
        'slug' => 'peugeot-208',
        'status' => 'published',
        'price' => '19990.50',
        'photo' => $media->id,
    ]);

    $product = collect(app(ComposeJsonLd::class)($type, $entry))->firstWhere('@type', 'Product');

    expect($product['name'])->toBe('Peugeot 208')
        ->and($product['image'])->toBe($media->url())
        ->and($product['offers'])->toBe(['price' => 19990.5, 'priceCurrency' => 'XOF']);
});

it('omits the schema.org node when the blueprint declares no mapping', function () {
    [$type, $modelClass] = buildJsonLdCar();
    $entry = $modelClass::create(['brand' => 'Peugeot 208', 'slug' => 'peugeot-208', 'status' => 'published', 'price' => 0]);

    $graph = app(ComposeJsonLd::class)($type, $entry);

    expect(collect($graph)->pluck('@type'))->not->toContain('Product');
});

it('lets a module extend the JSON-LD graph via baobab.seo.jsonld', function () {
    Hook::modify('baobab.seo.jsonld', fn (array $nodes) => [...$nodes, ['@type' => 'Thing', 'aggregateRating' => ['ratingValue' => 4.5]]]);

    $graph = app(ComposeJsonLd::class)(null, null);

    expect(collect($graph)->pluck('@type'))->toContain('Thing');
});

it('renders a single application/ld+json script tag with the composed graph on a public page', function () {
    SeoSetting::current()->fill(['site_name' => 'Acme Motors'])->save();
    ReadingSetting::current()->fill(['mode' => null])->save();

    $response = $this->get('/');

    $response->assertOk()
        ->assertSee('<script type="application/ld+json">', false)
        ->assertSee('"@type":"Organization"', false)
        ->assertSee('"@type":"WebSite"', false);
});
