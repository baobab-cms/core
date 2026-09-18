<?php

use Baobab\Audit\Models\AuditEntry;
use Baobab\ContentTypes\Actions\BuildContentType;
use Baobab\ContentTypes\Models\ContentType;
use Baobab\Exports\Models\ExportJob;
use Baobab\Facades\Hook;
use Baobab\Media\Actions\UploadMedia;
use Baobab\Media\Models\MediaUsage;
use Baobab\Modules\Models\Module;
use Baobab\Modules\ModuleAutoloader;
use Baobab\System\Actions\ExportContent;
use Baobab\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('public');
    Storage::fake('local');
    File::deleteDirectory(generatedModulesPath());
    config(['baobab.content_types.modules_path' => generatedModulesPath()]);
    config(['baobab.modules.paths' => ['local' => [generatedModulesPath().'/*']]]);
});

afterEach(function (): void {
    File::deleteDirectory(generatedModulesPath());
});

function exportContentActor(): User
{
    static $counter = 0;
    $counter++;

    return User::create([
        'name' => "Export Actor {$counter}",
        'email' => "export-actor-{$counter}@example.com",
        'password' => 'secret',
    ]);
}

/**
 * @return array{0: ContentType, 1: class-string<Model>}
 */
function buildExportAuthorType(string $key = 'ExportedAuthor'): array
{
    $contentType = app(BuildContentType::class)(contentTypeBlueprintJson($key, [
        'fields' => [['key' => 'name', 'type' => 'text']],
    ]));

    app(ModuleAutoloader::class)->registerFor(Module::findOrFail($contentType->module_id));

    /** @var class-string<Model> $modelClass */
    $modelClass = $contentType->modelClass();

    return [$contentType, $modelClass];
}

/**
 * @param  list<array<string, mixed>>  $relations
 * @return array{0: ContentType, 1: class-string<Model>}
 */
function buildExportArticleType(string $key = 'ExportedArticle', array $relations = []): array
{
    $contentType = app(BuildContentType::class)(contentTypeBlueprintJson($key, [
        'is_addressable' => true,
        'title_field' => 'title',
        'fields' => [
            ['key' => 'title', 'type' => 'text'],
            ['key' => 'cover', 'type' => 'image'],
            ['key' => 'photos', 'type' => 'gallery'],
        ],
        'relations' => $relations,
    ]));

    app(ModuleAutoloader::class)->registerFor(Module::findOrFail($contentType->module_id));

    /** @var class-string<Model> $modelClass */
    $modelClass = $contentType->modelClass();

    return [$contentType, $modelClass];
}

/**
 * @return array<int, array<string, mixed>>
 */
function readNdjson(string $contents): array
{
    if (trim($contents) === '') {
        return [];
    }

    return array_map(
        fn (string $line): array => (array) json_decode($line, associative: true),
        explode("\n", trim($contents)),
    );
}

it('exports records with the manifest, blueprint and sha256 checksums', function () {
    [, $authorClass] = buildExportAuthorType();

    /** @var Model $entry */
    $entry = new $authorClass(['name' => 'Amadou Kane']);
    $entry->save();

    $exportJob = ExportJob::create(['status' => 'pending', 'content_type_keys' => ['ExportedAuthor']]);

    expect(app(ExportContent::class)($exportJob))->toBeTrue();

    $exportJob->refresh();
    expect($exportJob->status)->toBe('completed')
        ->and($exportJob->file_path)->not->toBeNull()
        ->and($exportJob->file_size)->toBeGreaterThan(0);

    $zip = new ZipArchive;
    $zip->open((string) Storage::disk((string) $exportJob->file_disk)->path((string) $exportJob->file_path));

    $manifest = (array) json_decode((string) $zip->getFromName('manifest.json'), associative: true);
    expect($manifest['content_types'])->toBe(['ExportedAuthor'])
        ->and($manifest['counts']['records'])->toBe(1)
        ->and($manifest['checksums']['content/ExportedAuthor.ndjson'])
        ->toBe(hash('sha256', (string) $zip->getFromName('content/ExportedAuthor.ndjson')));

    $blueprint = (array) json_decode((string) $zip->getFromName('blueprints/ExportedAuthor.json'), associative: true);
    expect($blueprint['key'])->toBe('ExportedAuthor');

    $records = readNdjson((string) $zip->getFromName('content/ExportedAuthor.ndjson'));
    expect($records)->toHaveCount(1)
        ->and($records[0]['name'])->toBe('Amadou Kane')
        ->and($records[0]['uuid'])->toBe($entry->fresh()?->uuid);

    $zip->close();
});

it('resolves relation targets and media fields to portable slug/uuid identifiers', function () {
    [, $authorClass] = buildExportAuthorType();
    /** @var Model $author */
    $author = new $authorClass(['name' => 'Fatou Diop']);
    $author->save();

    // `written_by`, pas `author` : le socle éditorial (ContentTypeProfile::leadingColumns())
    // pose déjà une colonne structurelle `author_id` — une relation nommée
    // `author` entrerait en collision avec elle.
    [, $articleClass] = buildExportArticleType(relations: [
        ['key' => 'written_by', 'type' => 'one_to_many', 'target' => 'ExportedAuthor'],
    ]);

    $cover = app(UploadMedia::class)(new UploadedFile(createTestJpeg(), 'cover.jpg', 'image/jpeg', null, true), exportContentActor());

    /** @var Model $article */
    $article = new $articleClass([
        'title' => 'Hello world',
        'slug' => 'hello-world',
        'cover' => $cover->id,
        'written_by_id' => $author->getKey(),
    ]);
    $article->save();

    $exportJob = ExportJob::create(['status' => 'pending', 'content_type_keys' => ['ExportedArticle']]);
    app(ExportContent::class)($exportJob);

    $zip = new ZipArchive;
    $zip->open((string) Storage::disk((string) $exportJob->file_disk)->path((string) $exportJob->file_path));
    $records = readNdjson((string) $zip->getFromName('content/ExportedArticle.ndjson'));
    $zip->close();

    expect($records)->toHaveCount(1)
        ->and($records[0]['slug'])->toBe('hello-world')
        ->and($records[0]['cover'])->toBe($cover->uuid)
        ->and($records[0]['written_by'])->toBe($author->fresh()?->uuid);
});

it('exports a gallery field as an ordered list of media uuids and includes only referenced media', function () {
    [, $articleClass] = buildExportArticleType();

    $first = app(UploadMedia::class)(new UploadedFile(createTestJpeg(), 'first.jpg', 'image/jpeg', null, true), exportContentActor());
    $second = app(UploadMedia::class)(new UploadedFile(createTestJpeg(), 'second.jpg', 'image/jpeg', null, true), exportContentActor(), [], 'new');
    $unreferenced = app(UploadMedia::class)(new UploadedFile(createTestJpeg(), 'unused.jpg', 'image/jpeg', null, true), exportContentActor(), [], 'new');

    /** @var Model $article */
    $article = new $articleClass(['title' => 'Gallery', 'slug' => 'gallery']);
    $article->save();

    MediaUsage::create(['media_id' => $first->id, 'usable_type' => $article->getMorphClass(), 'usable_id' => $article->getKey(), 'field_key' => 'photos', 'order' => 0]);
    MediaUsage::create(['media_id' => $second->id, 'usable_type' => $article->getMorphClass(), 'usable_id' => $article->getKey(), 'field_key' => 'photos', 'order' => 1]);

    $exportJob = ExportJob::create(['status' => 'pending', 'content_type_keys' => ['ExportedArticle']]);
    app(ExportContent::class)($exportJob);

    $zip = new ZipArchive;
    $zip->open((string) Storage::disk((string) $exportJob->file_disk)->path((string) $exportJob->file_path));
    $records = readNdjson((string) $zip->getFromName('content/ExportedArticle.ndjson'));
    $mediaMetadata = readNdjson((string) $zip->getFromName('media/metadata.ndjson'));
    $fileBytes = $zip->getFromName('media/files/'.$first->uuid.'.jpg');
    $zip->close();

    expect($records[0]['photos'])->toBe([$first->uuid, $second->uuid])
        ->and($mediaMetadata)->toHaveCount(2)
        ->and(collect($mediaMetadata)->pluck('uuid')->all())->toEqualCanonicalizing([$first->uuid, $second->uuid])
        ->and(collect($mediaMetadata)->pluck('uuid'))->not->toContain($unreferenced->uuid)
        ->and($fileBytes)->toBe(Storage::disk('public')->get($first->path));
});

it('resolves a relation targeting the Core User model to null, users being excluded from export', function () {
    $actor = exportContentActor();

    [, $articleClass] = buildExportArticleType(relations: [
        ['key' => 'written_by', 'type' => 'one_to_many', 'target' => 'User'],
    ]);

    /** @var Model $article */
    $article = new $articleClass(['title' => 'Byline', 'slug' => 'byline', 'written_by_id' => $actor->id]);
    $article->save();

    $exportJob = ExportJob::create(['status' => 'pending', 'content_type_keys' => ['ExportedArticle']]);
    app(ExportContent::class)($exportJob);

    $zip = new ZipArchive;
    $zip->open((string) Storage::disk((string) $exportJob->file_disk)->path((string) $exportJob->file_path));
    $records = readNdjson((string) $zip->getFromName('content/ExportedArticle.ndjson'));
    $zip->close();

    expect($records[0]['written_by'])->toBeNull();
});

it('audits completion and fires baobab.export.completed', function () {
    buildExportAuthorType();

    $fired = null;
    Hook::listen('baobab.export.completed', function (ExportJob $job) use (&$fired): void {
        $fired = $job;
    });

    $exportJob = ExportJob::create(['status' => 'pending', 'content_type_keys' => ['ExportedAuthor']]);
    app(ExportContent::class)($exportJob);

    expect($fired)->toBeInstanceOf(ExportJob::class)
        ->and($fired->id)->toBe($exportJob->id)
        ->and(AuditEntry::where('action', 'export.completed')->exists())->toBeTrue();
});

it('marks the job failed and audits without throwing when the export disk is misconfigured', function () {
    buildExportAuthorType();

    config(['baobab.exports.disk' => 'this-disk-does-not-exist']);

    $exportJob = ExportJob::create(['status' => 'pending', 'content_type_keys' => ['ExportedAuthor']]);

    expect(app(ExportContent::class)($exportJob))->toBeFalse();

    $exportJob->refresh();
    expect($exportJob->status)->toBe('failed')
        ->and($exportJob->error_message)->not->toBeNull()
        ->and(AuditEntry::where('action', 'export.failed')->exists())->toBeTrue();
});
