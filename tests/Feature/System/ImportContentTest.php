<?php

use Baobab\Audit\Models\AuditEntry;
use Baobab\ContentTypes\Actions\BuildContentType;
use Baobab\ContentTypes\Models\ContentType;
use Baobab\Exports\Models\ExportJob;
use Baobab\Facades\Hook;
use Baobab\Imports\Models\ImportJob;
use Baobab\Media\Actions\UploadMedia;
use Baobab\Media\Models\Media;
use Baobab\Media\Models\MediaUsage;
use Baobab\Modules\Models\Module;
use Baobab\Modules\ModuleAutoloader;
use Baobab\System\Actions\ExportContent;
use Baobab\System\Actions\ImportContent;
use Baobab\System\Actions\ValidateImport;
use Baobab\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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

function importContentActor(): User
{
    static $counter = 0;
    $counter++;

    return User::create([
        'name' => "Import Actor {$counter}",
        'email' => "import-actor-{$counter}@example.com",
        'password' => 'secret',
    ]);
}

/**
 * @return array{0: ContentType, 1: class-string<Model>}
 */
function buildImportAuthorType(string $key = 'ImportedAuthor'): array
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
function buildImportArticleType(string $key = 'ImportedArticle', array $relations = []): array
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
 * Exporte les content types donnés (déjà construits, données déjà en base)
 * via la vraie Action d'export — les tests d'import rejouent l'archive
 * réelle plutôt qu'un fichier construit à la main, patron le plus fidèle au
 * format réel.
 *
 * @param  list<string>  $keys
 */
function exportArchivePath(array $keys): string
{
    $exportJob = ExportJob::create(['status' => 'pending', 'content_type_keys' => $keys]);

    if (! app(ExportContent::class)($exportJob)) {
        throw new RuntimeException("Export failed: {$exportJob->error_message}");
    }

    return (string) Storage::disk((string) $exportJob->file_disk)->path((string) $exportJob->file_path);
}

/**
 * Archive construite à la main (manifest + fichiers donnés) — pour les
 * scénarios où le contenu doit être indépendant de tout état local réel
 * (content type absent, version de format incompatible).
 *
 * @param  array<string, string>  $files  chemin relatif dans l'archive => contenu
 */
function buildRawArchive(array $files): string
{
    $path = sys_get_temp_dir().'/baobab-import-test-'.bin2hex(random_bytes(6)).'.zip';
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE);

    foreach ($files as $name => $contents) {
        $zip->addFromString($name, $contents);
    }

    $zip->close();

    return $path;
}

/**
 * Simule une cible qui n'a jamais eu ce content type : supprime la table, la
 * ligne `content_types` **et** la ligne `modules` qui porte son nom
 * (`InstallModule` en unique la colonne `name` — sans ça, un rebuild via
 * `BuildContentType` échoue sur ce doublon, jamais rencontré sur une vraie
 * instance cible qui n'a jamais construit ce type).
 */
function forgetContentType(string $key): void
{
    $contentType = ContentType::where('key', $key)->firstOrFail();
    Schema::dropIfExists($contentType->table_name);
    Module::where('id', $contentType->module_id)->delete();
    $contentType->delete();
}

it('reports a missing content type as "will create" in dry-run, without creating it', function () {
    [, $authorClass] = buildImportAuthorType('ImportedAuthorDryRun');
    /** @var Model $entry */
    $entry = new $authorClass(['name' => 'Aïssatou Ba']);
    $entry->save();

    $archivePath = exportArchivePath(['ImportedAuthorDryRun']);

    forgetContentType('ImportedAuthorDryRun');

    $report = app(ValidateImport::class)($archivePath, 'ignore');

    expect($report->isValid())->toBeTrue()
        ->and($report->contentTypes)->toHaveCount(1)
        ->and($report->contentTypes[0]->willCreate)->toBeTrue()
        ->and(ContentType::where('key', 'ImportedAuthorDryRun')->exists())->toBeFalse();
});

it('creates a missing content type for real and imports its rows', function () {
    // Bâtie à la main plutôt qu'en rejouant un vrai export : un type
    // reconstruit dans le même process après suppression retomberait sur le
    // suivi des migrations déjà exécutées de Laravel (table migrations,
    // distincte du schéma qu'on a dropé à la main) — piège de test, jamais
    // rencontré sur une vraie instance cible qui n'a jamais construit ce
    // type. `ImportedFreshType` n'a jamais existé dans ce process.
    $archivePath = buildRawArchive([
        'manifest.json' => (string) json_encode([
            'manifest_version' => '0.1',
            'content_types' => ['ImportedFreshType'],
        ]),
        'blueprints/ImportedFreshType.json' => (string) json_encode([
            'key' => 'ImportedFreshType',
            'label' => ['singular' => 'ImportedFreshType', 'plural' => 'ImportedFreshTypes'],
            'fields' => [['key' => 'name', 'type' => 'text']],
        ]),
        'content/ImportedFreshType.ndjson' => (string) json_encode([
            'id' => 1,
            'status' => 'draft',
            'published_at' => null,
            'uuid' => (string) Str::uuid(),
            'name' => 'Moussa Traoré',
        ]),
    ]);

    $importJob = ImportJob::create(['status' => 'pending', 'archive_path' => $archivePath, 'strategy' => 'ignore']);

    expect(app(ImportContent::class)($importJob))->toBeTrue();

    $importJob->refresh();
    expect($importJob->status)->toBe('completed');

    $created = ContentType::where('key', 'ImportedFreshType')->first();
    expect($created)->not->toBeNull();

    /** @var class-string<Model> $createdClass */
    $createdClass = $created->modelClass();
    expect($createdClass::where('name', 'Moussa Traoré')->exists())->toBeTrue();
});

it('ignores an existing row on collision with the ignore strategy', function () {
    [, $authorClass] = buildImportAuthorType('ImportedAuthorIgnore');
    /** @var Model $entry */
    $entry = new $authorClass(['name' => 'Original']);
    $entry->save();

    $archivePath = exportArchivePath(['ImportedAuthorIgnore']);

    // Change locale après l'export : l'import doit la laisser intacte.
    $entry->update(['name' => 'Changed locally']);

    $importJob = ImportJob::create(['status' => 'pending', 'archive_path' => $archivePath, 'strategy' => 'ignore']);
    app(ImportContent::class)($importJob);

    expect($authorClass::count())->toBe(1)
        ->and($entry->fresh()?->name)->toBe('Changed locally');

    $importJob->refresh();
    expect($importJob->report['content_types'][0]['skipped'])->toBe(1);
});

it('updates an existing row on collision with the replace strategy', function () {
    [, $authorClass] = buildImportAuthorType('ImportedAuthorReplace');
    /** @var Model $entry */
    $entry = new $authorClass(['name' => 'Original']);
    $entry->save();

    $archivePath = exportArchivePath(['ImportedAuthorReplace']);

    $entry->update(['name' => 'Changed locally']);

    $importJob = ImportJob::create(['status' => 'pending', 'archive_path' => $archivePath, 'strategy' => 'replace']);
    app(ImportContent::class)($importJob);

    expect($authorClass::count())->toBe(1)
        ->and($entry->fresh()?->name)->toBe('Original');
});

it('inserts a disambiguated duplicate on collision with the duplicate strategy', function () {
    [, $articleClass] = buildImportArticleType('ImportedArticleDuplicate');
    /** @var Model $entry */
    $entry = new $articleClass(['title' => 'Hello', 'slug' => 'hello']);
    $entry->save();

    $archivePath = exportArchivePath(['ImportedArticleDuplicate']);

    $importJob = ImportJob::create(['status' => 'pending', 'archive_path' => $archivePath, 'strategy' => 'duplicate']);
    app(ImportContent::class)($importJob);

    expect($articleClass::count())->toBe(2)
        ->and($articleClass::where('slug', 'hello-2')->exists())->toBeTrue();
});

it('resolves relations across two content types imported together (two-pass)', function () {
    [, $authorClass] = buildImportAuthorType('ImportedAuthorCross');
    /** @var Model $author */
    $author = new $authorClass(['name' => 'Cheikh Fall']);
    $author->save();

    [, $articleClass] = buildImportArticleType('ImportedArticleCross', relations: [
        ['key' => 'written_by', 'type' => 'one_to_many', 'target' => 'ImportedAuthorCross'],
    ]);
    /** @var Model $article */
    $article = new $articleClass(['title' => 'Cross', 'slug' => 'cross', 'written_by_id' => $author->getKey()]);
    $article->save();

    $archivePath = exportArchivePath(['ImportedAuthorCross', 'ImportedArticleCross']);

    // Les deux types sont ré-importés dans la même base : collision sur les
    // deux, stratégie replace pour rester déterministe et vérifier que la
    // relation est réécrite vers le même auteur (retrouvé par UUID).
    $importJob = ImportJob::create(['status' => 'pending', 'archive_path' => $archivePath, 'strategy' => 'replace']);
    expect(app(ImportContent::class)($importJob))->toBeTrue();

    expect($article->fresh()?->written_by_id)->toBe($author->getKey());
});

it('reuses an existing media by checksum instead of duplicating it, and resolves image/gallery fields', function () {
    [, $articleClass] = buildImportArticleType('ImportedArticleMedia');

    $cover = app(UploadMedia::class)(new UploadedFile(createTestJpeg(), 'cover.jpg', 'image/jpeg', null, true), importContentActor());
    $gallery1 = app(UploadMedia::class)(new UploadedFile(createTestJpeg(), 'g1.jpg', 'image/jpeg', null, true), importContentActor(), [], 'new');

    /** @var Model $article */
    $article = new $articleClass(['title' => 'Media', 'slug' => 'media', 'cover' => $cover->id]);
    $article->save();

    MediaUsage::create(['media_id' => $gallery1->id, 'usable_type' => $article->getMorphClass(), 'usable_id' => $article->getKey(), 'field_key' => 'photos', 'order' => 0]);

    $archivePath = exportArchivePath(['ImportedArticleMedia']);

    $mediaCountBefore = Media::count();

    $importJob = ImportJob::create(['status' => 'pending', 'archive_path' => $archivePath, 'strategy' => 'replace']);
    app(ImportContent::class)($importJob);

    // Les deux médias déjà présents localement (même checksum) sont réutilisés,
    // aucun nouveau Media créé pour cette ré-importation.
    expect(Media::count())->toBe($mediaCountBefore)
        ->and($article->fresh()?->cover)->toBe($cover->id);
});

it('refuses an incompatible format version without touching anything', function () {
    $archivePath = buildRawArchive([
        'manifest.json' => (string) json_encode(['manifest_version' => '99.0', 'content_types' => []]),
    ]);

    $report = app(ValidateImport::class)($archivePath, 'ignore');

    expect($report->isValid())->toBeFalse()
        ->and($report->errors)->not->toBeEmpty();
});

it('audits validation and completion, and fires the import hooks', function () {
    [, $authorClass] = buildImportAuthorType('ImportedAuthorHooks');
    /** @var Model $entry */
    $entry = new $authorClass(['name' => 'Hooked']);
    $entry->save();

    $archivePath = exportArchivePath(['ImportedAuthorHooks']);

    $validatedFired = null;
    Hook::listen('baobab.import.validated', function ($report) use (&$validatedFired): void {
        $validatedFired = $report;
    });

    app(ValidateImport::class)($archivePath, 'ignore');

    expect($validatedFired)->not->toBeNull()
        ->and(AuditEntry::where('action', 'import.validated')->exists())->toBeTrue();

    $completedFired = null;
    Hook::listen('baobab.import.completed', function ($job, $report) use (&$completedFired): void {
        $completedFired = $job;
    });

    $importJob = ImportJob::create(['status' => 'pending', 'archive_path' => $archivePath, 'strategy' => 'ignore']);
    app(ImportContent::class)($importJob);

    expect($completedFired)->toBeInstanceOf(ImportJob::class)
        ->and($completedFired->id)->toBe($importJob->id)
        ->and(AuditEntry::where('action', 'import.completed')->exists())->toBeTrue();
});

it('marks the job failed and audits without throwing on a corrupted archive', function () {
    $archivePath = sys_get_temp_dir().'/baobab-import-corrupt-'.bin2hex(random_bytes(6)).'.zip';
    file_put_contents($archivePath, 'not a real zip file');

    $importJob = ImportJob::create(['status' => 'pending', 'archive_path' => $archivePath, 'strategy' => 'ignore']);

    expect(app(ImportContent::class)($importJob))->toBeFalse();

    $importJob->refresh();
    expect($importJob->status)->toBe('failed')
        ->and($importJob->error_message)->not->toBeNull()
        ->and(AuditEntry::where('action', 'import.failed')->exists())->toBeTrue();
});
