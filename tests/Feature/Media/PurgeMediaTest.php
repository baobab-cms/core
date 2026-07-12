<?php

use Baobab\ContentTypes\Actions\BuildContentType;
use Baobab\Media\Actions\PurgeMedia;
use Baobab\Media\Actions\SyncMediaUsagesFromEntry;
use Baobab\Media\Actions\TransformMedia;
use Baobab\Media\Actions\UploadMedia;
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

function purgeMediaActor(): User
{
    static $counter = 0;
    $counter++;

    return User::create([
        'name' => "Purge Media Actor {$counter}",
        'email' => "purge-media-actor-{$counter}@example.com",
        'password' => 'secret',
    ]);
}

it('deletes the original, the edited version, all conversion files, related usages, and the row itself', function () {
    $media = app(UploadMedia::class)(
        new UploadedFile(createTestJpeg(400, 300), 'photo.jpg', 'image/jpeg', null, true),
        purgeMediaActor(),
    );
    $media = app(TransformMedia::class)($media, new UploadedFile(createTestJpeg(100, 100), 'edit.jpg', 'image/jpeg', null, true));
    $media = $media->fresh();

    $originalPath = $media->path;
    $editedPath = $media->edited_path;
    $conversionPaths = collect($media->conversions)->flatMap(fn (array $c) => $c['formats'])->values()->all();

    expect($conversionPaths)->not->toBeEmpty();

    $contentType = app(BuildContentType::class)(carBlueprintJson([
        'key' => 'PurgeArticle',
        'label' => ['singular' => 'PurgeArticle', 'plural' => 'PurgeArticles'],
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

    expect(MediaUsage::where('media_id', $media->id)->exists())->toBeTrue();

    app(PurgeMedia::class)($media);

    Storage::disk('public')->assertMissing($originalPath);
    Storage::disk('public')->assertMissing($editedPath);
    foreach ($conversionPaths as $path) {
        Storage::disk('public')->assertMissing($path);
    }

    expect(MediaUsage::where('media_id', $media->id)->exists())->toBeFalse()
        ->and(Media::withTrashed()->find($media->id))->toBeNull();
});
