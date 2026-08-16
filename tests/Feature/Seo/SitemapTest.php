<?php

use Baobab\ContentTypes\Actions\BuildContentType;
use Baobab\ContentTypes\Actions\SaveContentEntry;
use Baobab\ContentTypes\Models\ContentType;
use Baobab\Facades\Hook;
use Baobab\Media\Models\Media;
use Baobab\Modules\Models\Module;
use Baobab\Modules\ModuleAutoloader;
use Baobab\Seo\Actions\RenderContentTypeSitemap;
use Baobab\Seo\Actions\RenderSitemapIndex;
use Baobab\Seo\Actions\UpdateSeoSettings;
use Baobab\Seo\Models\SeoContentTypeSetting;
use Baobab\Seo\Models\SeoMeta;
use Baobab\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
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
function buildSitemapCar(): array
{
    $contentType = app(BuildContentType::class)(contentTypeBlueprintJson('SitemapPage', [
        'is_addressable' => true,
        'title_field' => 'brand',
        'fields' => [
            ['key' => 'brand', 'type' => 'text', 'required' => true],
            ['key' => 'photo', 'type' => 'image'],
        ],
    ]));
    $module = Module::findOrFail($contentType->module_id);
    app(ModuleAutoloader::class)->registerFor($module);

    /** @var class-string<Model> $modelClass */
    $modelClass = $contentType->modelClass();

    return [$contentType->fresh(), $modelClass];
}

function sitemapTestMedia(): Media
{
    return Media::create([
        'disk' => 'public',
        'path' => 'media/2026/07/car.jpg',
        'file_name' => 'car.jpg',
        'mime_type' => 'image/jpeg',
        'size' => 1000,
        'checksum' => hash('sha256', 'sitemap-test-'.uniqid()),
    ]);
}

it('lists a published entry with its lastmod date', function () {
    [, $modelClass] = buildSitemapCar();
    $entry = $modelClass::create(['brand' => 'Peugeot 208', 'slug' => 'peugeot-208', 'status' => 'published']);

    $xml = $this->get('/sitemaps/sitemap-pages.xml')->assertOk()->getContent();

    expect($xml)->toContain(url('/sitemap-pages/peugeot-208'))
        ->and($xml)->toContain($entry->updated_at->format('Y-m-d'));
});

it('excludes a draft entry', function () {
    [, $modelClass] = buildSitemapCar();
    $modelClass::create(['brand' => 'Draft', 'slug' => 'draft-car', 'status' => 'draft']);

    $xml = $this->get('/sitemaps/sitemap-pages.xml')->assertOk()->getContent();

    expect($xml)->not->toContain('draft-car');
});

it('excludes an entry flagged noindex on its SEO metabox', function () {
    [, $modelClass] = buildSitemapCar();
    $entry = $modelClass::create(['brand' => 'Hidden', 'slug' => 'hidden-car', 'status' => 'published']);
    SeoMeta::forEntry($entry)->fill(['robots_noindex' => true])->save();

    $xml = $this->get('/sitemaps/sitemap-pages.xml')->assertOk()->getContent();

    expect($xml)->not->toContain('hidden-car');
});

it('includes the main image field as an image:image tag', function () {
    [, $modelClass] = buildSitemapCar();
    $media = sitemapTestMedia();
    $modelClass::create(['brand' => 'Peugeot 208', 'slug' => 'peugeot-208', 'status' => 'published', 'photo' => $media->id]);

    $xml = $this->get('/sitemaps/sitemap-pages.xml')->assertOk()->getContent();

    expect($xml)->toContain('image:image')->and($xml)->toContain($media->url());
});

it('lists the type in the sitemap index', function () {
    buildSitemapCar();

    $xml = $this->get('/sitemap.xml')->assertOk()->getContent();

    expect($xml)->toContain(url('/sitemaps/sitemap-pages.xml'));
});

it('excludes a content type flagged exclude_from_sitemap from the index and 404s its own sitemap', function () {
    [$type] = buildSitemapCar();
    SeoContentTypeSetting::forContentType($type)->fill(['exclude_from_sitemap' => true])->save();

    $indexXml = $this->get('/sitemap.xml')->assertOk()->getContent();
    expect($indexXml)->not->toContain('sitemap-pages.xml');

    $this->get('/sitemaps/sitemap-pages.xml')->assertNotFound();
});

it('returns 404 for an unknown type sitemap', function () {
    $this->get('/sitemaps/does-not-exist.xml')->assertNotFound();
});

it('lets a module extend the sitemap index via baobab.seo.sitemap.sources', function () {
    buildSitemapCar();

    Hook::modify('baobab.seo.sitemap.sources', fn (array $urls) => [...$urls, 'https://example.com/external-sitemap.xml']);

    $xml = $this->get('/sitemap.xml')->assertOk()->getContent();

    expect($xml)->toContain('https://example.com/external-sitemap.xml');
});

it('invalidates only the affected type\'s cache when one of its entries is saved', function () {
    [$type] = buildSitemapCar();
    $this->get('/sitemap.xml');
    $this->get('/sitemaps/sitemap-pages.xml');

    expect(Cache::has(RenderSitemapIndex::CACHE_KEY))->toBeTrue()
        ->and(Cache::has(RenderContentTypeSitemap::cacheKey('SitemapPage')))->toBeTrue();

    $actor = User::create(['name' => 'Actor', 'email' => 'sitemap-actor-'.uniqid().'@example.com', 'password' => 'secret']);
    app(SaveContentEntry::class)($type, ['brand' => 'New', 'slug' => 'new-car', 'status' => 'published'], $actor);

    expect(Cache::has(RenderContentTypeSitemap::cacheKey('SitemapPage')))->toBeFalse()
        ->and(Cache::has(RenderSitemapIndex::CACHE_KEY))->toBeTrue();
});

it('invalidates the whole sitemap cache when a type toggles exclude_from_sitemap via admin/seo', function () {
    buildSitemapCar();
    $this->get('/sitemap.xml');
    $this->get('/sitemaps/sitemap-pages.xml');

    expect(Cache::has(RenderSitemapIndex::CACHE_KEY))->toBeTrue()
        ->and(Cache::has(RenderContentTypeSitemap::cacheKey('SitemapPage')))->toBeTrue();

    app(UpdateSeoSettings::class)(
        ['title_separator' => '—'],
        ['SitemapPage' => ['exclude_from_sitemap' => true]],
    );

    expect(Cache::has(RenderSitemapIndex::CACHE_KEY))->toBeFalse()
        ->and(Cache::has(RenderContentTypeSitemap::cacheKey('SitemapPage')))->toBeFalse();
});

it('regenerates and warms the sitemap cache via the seo:sitemap command', function () {
    buildSitemapCar();

    $this->artisan('seo:sitemap')->assertSuccessful();

    expect(Cache::has(RenderSitemapIndex::CACHE_KEY))->toBeTrue()
        ->and(Cache::has(RenderContentTypeSitemap::cacheKey('SitemapPage')))->toBeTrue();
});
