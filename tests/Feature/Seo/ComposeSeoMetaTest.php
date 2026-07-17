<?php

use Baobab\ContentTypes\Actions\BuildContentType;
use Baobab\ContentTypes\Models\ContentType;
use Baobab\Facades\Hook;
use Baobab\Media\Models\Media;
use Baobab\Modules\Models\Module;
use Baobab\Modules\ModuleAutoloader;
use Baobab\Seo\Actions\ComposeSeoMeta;
use Baobab\Seo\Models\SeoContentTypeSetting;
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
 * Clé dédiée (« SeoCar »), même raison que buildAddressableCar() dans
 * PublicContentRenderingTest.php : éviter la classe Eloquent « Car » figée
 * par un autre fichier de test en exécution séquentielle.
 *
 * @param  array<string, mixed>  $overrides
 * @return array{0: ContentType, 1: class-string<Model>}
 */
function buildSeoCar(array $overrides = []): array
{
    $contentType = app(BuildContentType::class)(carBlueprintJson(array_replace([
        'key' => 'SeoCar',
        'is_addressable' => true,
        'title_field' => 'brand',
        'fields' => [
            ['key' => 'brand', 'type' => 'text', 'required' => true],
            ['key' => 'description', 'type' => 'richtext'],
            ['key' => 'photo', 'type' => 'image'],
        ],
    ], $overrides)));

    $module = Module::findOrFail($contentType->module_id);
    app(ModuleAutoloader::class)->registerFor($module);

    /** @var class-string<Model> $modelClass */
    $modelClass = $contentType->modelClass();

    return [$contentType->fresh(), $modelClass];
}

/**
 * `description` (richtext) n'est pas nullable en base (RichTextField::columnDefinition()
 * ne le déclare jamais nullable, indépendamment de `required` au blueprint) —
 * toujours fournie ici, vide par défaut, sauf pour les tests qui testent
 * spécifiquement son extraction.
 *
 * @param  class-string<Model>  $modelClass
 * @param  array<string, mixed>  $overrides
 */
function createSeoCarEntry(string $modelClass, array $overrides = []): Model
{
    return $modelClass::create(array_replace([
        'brand' => 'Peugeot 208',
        'slug' => 'peugeot-208',
        'status' => 'published',
        'description' => '',
    ], $overrides));
}

function seoTestMedia(): Media
{
    return Media::create([
        'disk' => 'public',
        'path' => 'media/2026/07/car.jpg',
        'file_name' => 'car.jpg',
        'mime_type' => 'image/jpeg',
        'size' => 1000,
        'checksum' => hash('sha256', 'seo-test-'.uniqid()),
    ]);
}

it('falls back to the raw entry title and article og:type when nothing is configured', function () {
    [$type, $modelClass] = buildSeoCar();
    $entry = createSeoCarEntry($modelClass);

    $seo = app(ComposeSeoMeta::class)($type, $entry);

    expect($seo['title'])->toBe('Peugeot 208')
        ->and($seo['og_type'])->toBe('article')
        ->and($seo['robots_noindex'])->toBeFalse();
});

it('prefers the metabox meta_title over the raw entry title', function () {
    [$type, $modelClass] = buildSeoCar();
    $entry = createSeoCarEntry($modelClass);
    SeoMeta::forEntry($entry)->fill(['meta_title' => 'Custom title'])->save();

    $seo = app(ComposeSeoMeta::class)($type, $entry);

    expect($seo['title'])->toBe('Custom title');
});

it('applies the content type title template when no meta_title is saved', function () {
    [$type, $modelClass] = buildSeoCar();
    $entry = createSeoCarEntry($modelClass);
    SeoContentTypeSetting::forContentType($type)->fill(['title_template' => '{title} — {site_name}'])->save();
    SeoSetting::current()->fill(['site_name' => 'Acme'])->save();

    $seo = app(ComposeSeoMeta::class)($type, $entry);

    expect($seo['title'])->toBe('Peugeot 208 — Acme');
});

it('extracts the description from the first richtext field when meta_description is empty', function () {
    [$type, $modelClass] = buildSeoCar();
    $entry = createSeoCarEntry($modelClass, ['description' => '<p>Une <strong>berline</strong> compacte.</p>']);

    $seo = app(ComposeSeoMeta::class)($type, $entry);

    expect($seo['description'])->toBe('Une berline compacte.');
});

it('falls back to the global default description when no richtext field has content', function () {
    [$type, $modelClass] = buildSeoCar();
    $entry = createSeoCarEntry($modelClass);
    SeoSetting::current()->fill(['default_meta_description' => 'Global fallback'])->save();

    $seo = app(ComposeSeoMeta::class)($type, $entry);

    expect($seo['description'])->toBe('Global fallback');
});

it('resolves og:image from the first image field on the entry when no metabox override exists', function () {
    [$type, $modelClass] = buildSeoCar();
    $media = seoTestMedia();
    $entry = createSeoCarEntry($modelClass, ['photo' => $media->id]);

    $seo = app(ComposeSeoMeta::class)($type, $entry);

    expect($seo['og_image_url'])->toBe($media->url());
});

it('prefers the metabox og image over the entry field image', function () {
    [$type, $modelClass] = buildSeoCar();
    $fieldMedia = seoTestMedia();
    $metaMedia = seoTestMedia();
    $entry = createSeoCarEntry($modelClass, ['photo' => $fieldMedia->id]);
    SeoMeta::forEntry($entry)->fill(['og_image_media_id' => $metaMedia->id])->save();

    $seo = app(ComposeSeoMeta::class)($type, $entry);

    expect($seo['og_image_url'])->toBe($metaMedia->url());
});

it('falls back to the global default share image when nothing else is set', function () {
    [$type, $modelClass] = buildSeoCar();
    $default = seoTestMedia();
    SeoSetting::current()->fill(['default_share_media_id' => $default->id])->save();
    $entry = createSeoCarEntry($modelClass);

    $seo = app(ComposeSeoMeta::class)($type, $entry);

    expect($seo['og_image_url'])->toBe($default->url());
});

it('reflects the noindex/nofollow directives saved on the metabox', function () {
    [$type, $modelClass] = buildSeoCar();
    $entry = createSeoCarEntry($modelClass);
    SeoMeta::forEntry($entry)->fill(['robots_noindex' => true, 'robots_nofollow' => true])->save();

    $seo = app(ComposeSeoMeta::class)($type, $entry);

    expect($seo['robots_noindex'])->toBeTrue()
        ->and($seo['robots_nofollow'])->toBeTrue();
});

it('composes archive-level meta from the content type label and website og:type, without an entry', function () {
    [$type] = buildSeoCar();

    $seo = app(ComposeSeoMeta::class)($type, null);

    expect($seo['title'])->toBe($type->blueprint['label']['plural'])
        ->and($seo['og_type'])->toBe('website');
});

it('composes site-level meta from the site name alone when no content type is given', function () {
    SeoSetting::current()->fill(['site_name' => 'Acme'])->save();

    $seo = app(ComposeSeoMeta::class)(null, null);

    expect($seo['title'])->toBe('Acme')
        ->and($seo['site_name'])->toBe('Acme')
        ->and($seo['og_type'])->toBe('website');
});

it('lets a module transform the composed meta via the baobab.seo.meta filter', function () {
    [$type, $modelClass] = buildSeoCar();
    $entry = createSeoCarEntry($modelClass);

    Hook::modify('baobab.seo.meta', function (array $seo) {
        $seo['title'] = 'Overridden by a module';

        return $seo;
    });

    $seo = app(ComposeSeoMeta::class)($type, $entry);

    expect($seo['title'])->toBe('Overridden by a module');
});
