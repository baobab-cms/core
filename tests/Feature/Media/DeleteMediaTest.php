<?php

use Baobab\ContentTypes\Actions\BuildContentType;
use Baobab\Media\Actions\DeleteMedia;
use Baobab\Media\Actions\SyncMediaUsagesFromEntry;
use Baobab\Media\Actions\UploadMedia;
use Baobab\Media\Exceptions\MediaInUseException;
use Baobab\Media\Models\Media;
use Baobab\Modules\Models\Module;
use Baobab\Modules\ModuleAutoloader;
use Baobab\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    File::deleteDirectory(generatedModulesPath());
    config(['baobab.content_types.modules_path' => generatedModulesPath()]);
    config(['baobab.modules.paths' => ['local' => [generatedModulesPath().'/*']]]);
});

afterEach(function () {
    File::deleteDirectory(generatedModulesPath());
});

beforeEach(function () {
    Storage::fake('public');
});

function deleteMediaActor(): User
{
    static $counter = 0;
    $counter++;

    return User::create([
        'name' => "Delete Media Actor {$counter}",
        'email' => "delete-media-actor-{$counter}@example.com",
        'password' => 'secret',
    ]);
}

it('soft deletes a media, leaving the file on disk', function () {
    $media = app(UploadMedia::class)(
        new UploadedFile(createTestJpeg(), 'photo.jpg', 'image/jpeg', null, true),
        deleteMediaActor(),
    );
    $path = $media->path;

    app(DeleteMedia::class)($media);

    expect(Media::find($media->id))->toBeNull()
        ->and(Media::withTrashed()->find($media->id))->not->toBeNull();

    Storage::disk('public')->assertExists($path);
});

it('refuses to delete a media referenced by a media usage', function () {
    $media = app(UploadMedia::class)(
        new UploadedFile(createTestJpeg(), 'used.jpg', 'image/jpeg', null, true),
        deleteMediaActor(),
    );

    $contentType = app(BuildContentType::class)(carBlueprintJson([
        'key' => 'DeleteGuardArticle',
        'label' => ['singular' => 'DeleteGuardArticle', 'plural' => 'DeleteGuardArticles'],
        'fields' => [['key' => 'body', 'type' => 'richtext']],
    ]));
    $module = Module::findOrFail($contentType->module_id);
    app(ModuleAutoloader::class)->registerFor($module);

    /** @var class-string<Model> $modelClass */
    $modelClass = $contentType->modelClass();
    /** @var Model $entry */
    $entry = new $modelClass(['body' => "<img src=\"{$media->url()}\">"]);
    $entry->save();
    app(SyncMediaUsagesFromEntry::class)($contentType, $entry->fresh());

    expect(fn () => app(DeleteMedia::class)($media))
        ->toThrow(MediaInUseException::class);

    expect(Media::find($media->id))->not->toBeNull();
});

it('deletes a used media anyway when force is true', function () {
    $media = app(UploadMedia::class)(
        new UploadedFile(createTestJpeg(), 'forced.jpg', 'image/jpeg', null, true),
        deleteMediaActor(),
    );

    $contentType = app(BuildContentType::class)(carBlueprintJson([
        'key' => 'DeleteForceArticle',
        'label' => ['singular' => 'DeleteForceArticle', 'plural' => 'DeleteForceArticles'],
        'fields' => [['key' => 'body', 'type' => 'richtext']],
    ]));
    $module = Module::findOrFail($contentType->module_id);
    app(ModuleAutoloader::class)->registerFor($module);

    /** @var class-string<Model> $modelClass */
    $modelClass = $contentType->modelClass();
    /** @var Model $entry */
    $entry = new $modelClass(['body' => "<img src=\"{$media->url()}\">"]);
    $entry->save();
    app(SyncMediaUsagesFromEntry::class)($contentType, $entry->fresh());

    app(DeleteMedia::class)($media, force: true);

    expect(Media::find($media->id))->toBeNull();
});
