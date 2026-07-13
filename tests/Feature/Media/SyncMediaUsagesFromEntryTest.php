<?php

use Baobab\ContentTypes\Actions\BuildContentType;
use Baobab\ContentTypes\Models\ContentType;
use Baobab\Media\Actions\DeleteMedia;
use Baobab\Media\Actions\SyncMediaUsagesFromEntry;
use Baobab\Media\Actions\UploadMedia;
use Baobab\Media\Exceptions\MediaInUseException;
use Baobab\Media\Models\Media;
use Baobab\Media\Models\MediaUsage;
use Baobab\Modules\Models\Module;
use Baobab\Modules\ModuleAutoloader;
use Baobab\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    File::deleteDirectory(generatedModulesPath());
    config(['baobab.content_types.modules_path' => generatedModulesPath()]);
    config(['baobab.modules.paths' => ['local' => [generatedModulesPath().'/*']]]);
});

afterEach(function () {
    File::deleteDirectory(generatedModulesPath());
});

function syncUsagesActor(): User
{
    static $counter = 0;
    $counter++;

    return User::create([
        'name' => "Sync Usages Actor {$counter}",
        'email' => "sync-usages-actor-{$counter}@example.com",
        'password' => 'secret',
    ]);
}

/**
 * Construit un Content Type réel avec deux champs richtext (`body`/`notes`)
 * et retourne [contentType, la classe modèle générée chargeable].
 *
 * @return array{0: ContentType, 1: class-string<Model>}
 */
function buildRichTextContentType(string $key = 'ArticleUsage'): array
{
    $contentType = app(BuildContentType::class)(carBlueprintJson([
        'key' => $key,
        'label' => ['singular' => $key, 'plural' => $key.'s'],
        'fields' => [
            ['key' => 'body', 'type' => 'richtext'],
            ['key' => 'notes', 'type' => 'richtext'],
        ],
    ]));

    $module = Module::findOrFail($contentType->module_id);
    app(ModuleAutoloader::class)->registerFor($module);

    /** @var class-string<Model> $modelClass */
    $modelClass = $contentType->modelClass();

    return [$contentType, $modelClass];
}

it('creates media usages for images referenced in a richtext field on save', function () {
    $media = app(UploadMedia::class)(
        new UploadedFile(createTestJpeg(), 'photo.jpg', 'image/jpeg', null, true),
        syncUsagesActor(),
    );

    [$contentType, $modelClass] = buildRichTextContentType();

    /** @var Model $entry */
    $entry = new $modelClass(['body' => "<p>Texte</p><img src=\"{$media->url()}\">", 'notes' => '<p>Rien ici</p>']);
    $entry->save();

    app(SyncMediaUsagesFromEntry::class)($contentType, $entry->fresh());

    expect(MediaUsage::where('media_id', $media->id)->where('field_key', 'body')->exists())->toBeTrue()
        ->and(MediaUsage::where('media_id', $media->id)->where('field_key', 'notes')->exists())->toBeFalse();
});

it('replaces usages for a field instead of accumulating them when content changes', function () {
    $first = app(UploadMedia::class)(new UploadedFile(createTestJpeg(), 'first.jpg', 'image/jpeg', null, true), syncUsagesActor());
    $second = app(UploadMedia::class)(new UploadedFile(createTestJpeg(), 'second.jpg', 'image/jpeg', null, true), syncUsagesActor(), [], 'new');

    [$contentType, $modelClass] = buildRichTextContentType();

    /** @var Model $entry */
    $entry = new $modelClass(['body' => "<img src=\"{$first->url()}\">", 'notes' => '']);
    $entry->save();
    app(SyncMediaUsagesFromEntry::class)($contentType, $entry->fresh());

    expect(MediaUsage::where('field_key', 'body')->count())->toBe(1);

    $entry->update(['body' => "<img src=\"{$second->url()}\">"]);
    app(SyncMediaUsagesFromEntry::class)($contentType, $entry->fresh());

    $bodyUsages = MediaUsage::where('field_key', 'body')->get();
    expect($bodyUsages)->toHaveCount(1)
        ->and($bodyUsages->first()?->media_id)->toBe($second->id);
});

/**
 * Construit un Content Type réel avec un champ `gallery` (`photos`) et
 * retourne [contentType, la classe modèle générée chargeable].
 *
 * @return array{0: ContentType, 1: class-string<Model>}
 */
function buildGalleryContentType(string $key = 'PhotoAlbum'): array
{
    $contentType = app(BuildContentType::class)(carBlueprintJson([
        'key' => $key,
        'label' => ['singular' => $key, 'plural' => $key.'s'],
        'fields' => [
            ['key' => 'photos', 'type' => 'gallery'],
        ],
    ]));

    $module = Module::findOrFail($contentType->module_id);
    app(ModuleAutoloader::class)->registerFor($module);

    /** @var class-string<Model> $modelClass */
    $modelClass = $contentType->modelClass();

    return [$contentType, $modelClass];
}

it('creates ordered media usages for a gallery field on save', function () {
    $first = app(UploadMedia::class)(new UploadedFile(createTestJpeg(), 'first.jpg', 'image/jpeg', null, true), syncUsagesActor());
    $second = app(UploadMedia::class)(new UploadedFile(createTestJpeg(), 'second.jpg', 'image/jpeg', null, true), syncUsagesActor(), [], 'new');

    [$contentType, $modelClass] = buildGalleryContentType();

    /** @var Model $entry */
    $entry = new $modelClass;
    $entry->save();

    app(SyncMediaUsagesFromEntry::class)($contentType, $entry->fresh(), ['photos' => [$first->id, $second->id]]);

    $usages = MediaUsage::where('field_key', 'photos')->orderBy('order')->get();

    expect($usages)->toHaveCount(2)
        ->and($usages->get(0)?->media_id)->toBe($first->id)
        ->and($usages->get(0)?->order)->toBe(0)
        ->and($usages->get(1)?->media_id)->toBe($second->id)
        ->and($usages->get(1)?->order)->toBe(1);
});

it('replaces a gallery selection instead of accumulating it when resaved', function () {
    $first = app(UploadMedia::class)(new UploadedFile(createTestJpeg(), 'first.jpg', 'image/jpeg', null, true), syncUsagesActor());
    $second = app(UploadMedia::class)(new UploadedFile(createTestJpeg(), 'second.jpg', 'image/jpeg', null, true), syncUsagesActor(), [], 'new');

    [$contentType, $modelClass] = buildGalleryContentType();

    /** @var Model $entry */
    $entry = new $modelClass;
    $entry->save();

    app(SyncMediaUsagesFromEntry::class)($contentType, $entry->fresh(), ['photos' => [$first->id]]);
    expect(MediaUsage::where('field_key', 'photos')->count())->toBe(1);

    app(SyncMediaUsagesFromEntry::class)($contentType, $entry->fresh(), ['photos' => [$second->id]]);
    $usages = MediaUsage::where('field_key', 'photos')->get();

    expect($usages)->toHaveCount(1)
        ->and($usages->first()?->media_id)->toBe($second->id);
});

it('removes all gallery usages when resaved with an empty selection', function () {
    $media = app(UploadMedia::class)(new UploadedFile(createTestJpeg(), 'photo.jpg', 'image/jpeg', null, true), syncUsagesActor());

    [$contentType, $modelClass] = buildGalleryContentType();

    /** @var Model $entry */
    $entry = new $modelClass;
    $entry->save();

    app(SyncMediaUsagesFromEntry::class)($contentType, $entry->fresh(), ['photos' => [$media->id]]);
    expect(MediaUsage::where('field_key', 'photos')->count())->toBe(1);

    app(SyncMediaUsagesFromEntry::class)($contentType, $entry->fresh(), []);
    expect(MediaUsage::where('field_key', 'photos')->count())->toBe(0);
});

it('protects a media used in a gallery from deletion, unlike a media/file field FK', function () {
    $media = app(UploadMedia::class)(new UploadedFile(createTestJpeg(), 'photo.jpg', 'image/jpeg', null, true), syncUsagesActor());

    [$contentType, $modelClass] = buildGalleryContentType();

    /** @var Model $entry */
    $entry = new $modelClass;
    $entry->save();

    app(SyncMediaUsagesFromEntry::class)($contentType, $entry->fresh(), ['photos' => [$media->id]]);

    expect(fn () => app(DeleteMedia::class)($media->fresh()))
        ->toThrow(MediaInUseException::class);
});

it('does not touch usages of another field on the same entry when resyncing', function () {
    $media = app(UploadMedia::class)(new UploadedFile(createTestJpeg(), 'photo.jpg', 'image/jpeg', null, true), syncUsagesActor());

    [$contentType, $modelClass] = buildRichTextContentType();

    /** @var Model $entry */
    $entry = new $modelClass(['body' => '', 'notes' => "<img src=\"{$media->url()}\">"]);
    $entry->save();
    app(SyncMediaUsagesFromEntry::class)($contentType, $entry->fresh());

    expect(MediaUsage::where('field_key', 'notes')->count())->toBe(1);

    $entry->update(['body' => '<p>Un ajout sans image</p>']);
    app(SyncMediaUsagesFromEntry::class)($contentType, $entry->fresh());

    expect(MediaUsage::where('field_key', 'notes')->count())->toBe(1)
        ->and(Media::find($media->id)?->usages()->count())->toBe(1);
});
