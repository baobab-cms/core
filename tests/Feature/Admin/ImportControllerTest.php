<?php

use Baobab\Access\Actions\GrantPermission;
use Baobab\ContentTypes\Actions\BuildContentType;
use Baobab\Exports\Models\ExportJob;
use Baobab\Imports\Jobs\RunContentImportJob;
use Baobab\Imports\Models\ImportJob;
use Baobab\Modules\Models\Module;
use Baobab\Modules\ModuleAutoloader;
use Baobab\System\Actions\ExportContent;
use Baobab\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

/**
 * @param  list<string>  $permissions
 */
function importActor(array $permissions): User
{
    static $counter = 0;
    $counter++;

    $user = User::create([
        'name' => "Import Actor {$counter}",
        'email' => "import-controller-actor-{$counter}@example.com",
        'password' => 'secret',
    ]);

    app(GrantPermission::class)($user, 'baobab.admin.access');

    foreach ($permissions as $permission) {
        app(GrantPermission::class)($user, $permission);
    }

    return $user;
}

beforeEach(function (): void {
    Storage::fake('local');
    File::deleteDirectory(generatedModulesPath());
    config(['baobab.content_types.modules_path' => generatedModulesPath()]);
    config(['baobab.modules.paths' => ['local' => [generatedModulesPath().'/*']]]);
});

afterEach(function (): void {
    File::deleteDirectory(generatedModulesPath());
});

/**
 * Une vraie archive d'export, réutilisée pour poster comme un upload.
 */
function exportedArchiveUploadedFile(string $key): UploadedFile
{
    $contentType = app(BuildContentType::class)(contentTypeBlueprintJson($key, [
        'fields' => [['key' => 'name', 'type' => 'text']],
    ]));
    app(ModuleAutoloader::class)->registerFor(Module::findOrFail($contentType->module_id));
    /** @var class-string<Model> $modelClass */
    $modelClass = $contentType->modelClass();
    /** @var Model $entry */
    $entry = new $modelClass(['name' => 'Import Controller Row']);
    $entry->save();

    $exportJob = ExportJob::create(['status' => 'pending', 'content_type_keys' => [$key]]);
    app(ExportContent::class)($exportJob);

    $realPath = Storage::disk((string) $exportJob->file_disk)->path((string) $exportJob->file_path);

    return new UploadedFile($realPath, 'export.zip', 'application/zip', null, true);
}

it('denies the screen without baobab.system.import.view', function () {
    $this->actingAs(importActor([]), 'baobab')
        ->get(route('admin.system.import.index'))
        ->assertForbidden();
});

it('denies previewing an import without baobab.system.import.run', function () {
    $this->actingAs(importActor(['baobab.system.import.view']), 'baobab')
        ->post(route('admin.system.import.preview'), ['archive' => exportedArchiveUploadedFile('ImportScreenTypeA'), 'strategy' => 'ignore'])
        ->assertForbidden();
});

it('previews a valid archive, stores the pending import in session, and the report actually renders', function () {
    $actor = importActor(['baobab.system.import.view', 'baobab.system.import.run']);

    $this->actingAs($actor, 'baobab')
        ->post(route('admin.system.import.preview'), ['archive' => exportedArchiveUploadedFile('ImportScreenTypeB'), 'strategy' => 'ignore'])
        ->assertRedirect(route('admin.system.import.index'))
        ->assertSessionHas('baobab.import.pending')
        ->assertSessionHas('import_report');

    // Le rapport voyage en session au format `config('session.serialization')`
    // du projet (`json`, spec de sécurité contre les attaques par gadget
    // chain) : un objet flashé tel quel redescend en tableau, jamais
    // capturé par une simple assertion de présence — seul le rendu réel de
    // la vue l'aurait révélé (défaut réel trouvé en recette, pas ici).
    $this->actingAs($actor, 'baobab')
        ->get(route('admin.system.import.index'))
        ->assertOk()
        ->assertSee('ImportScreenTypeB');
});

it('rejects a file that is not a zip archive', function () {
    $bogus = UploadedFile::fake()->create('not-an-archive.txt', 10);

    $this->actingAs(importActor(['baobab.system.import.view', 'baobab.system.import.run']), 'baobab')
        ->post(route('admin.system.import.preview'), ['archive' => $bogus, 'strategy' => 'ignore'])
        ->assertRedirect(route('admin.system.import.index'))
        ->assertSessionMissing('baobab.import.pending');
});

it('creates an import job and dispatches it on baobab-low after confirmation', function () {
    Queue::fake();

    $actor = importActor(['baobab.system.import.view', 'baobab.system.import.run']);

    $this->actingAs($actor, 'baobab')
        ->post(route('admin.system.import.preview'), ['archive' => exportedArchiveUploadedFile('ImportScreenTypeC'), 'strategy' => 'replace']);

    $this->actingAs($actor, 'baobab')
        ->post(route('admin.system.import.create'))
        ->assertRedirect(route('admin.system.import.index'));

    $importJob = ImportJob::sole();
    expect($importJob->status)->toBe('pending')
        ->and($importJob->strategy)->toBe('replace');

    Queue::assertPushedOn('baobab-low', RunContentImportJob::class);
});

it('refuses to confirm without a pending preview', function () {
    $this->actingAs(importActor(['baobab.system.import.view', 'baobab.system.import.run']), 'baobab')
        ->post(route('admin.system.import.create'))
        ->assertStatus(400);
});
