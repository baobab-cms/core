<?php

use Baobab\ContentTypes\Actions\BuildContentType;
use Baobab\Exports\Models\ExportJob;
use Baobab\Imports\Models\ImportJob;
use Baobab\Modules\Models\Module;
use Baobab\Modules\ModuleAutoloader;
use Baobab\System\Actions\ExportContent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

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
 * @return array{0: string, 1: class-string<Model>}
 */
function cliImportArchive(string $key): array
{
    $contentType = app(BuildContentType::class)(contentTypeBlueprintJson($key, [
        'fields' => [['key' => 'name', 'type' => 'text']],
    ]));
    app(ModuleAutoloader::class)->registerFor(Module::findOrFail($contentType->module_id));
    /** @var class-string<Model> $modelClass */
    $modelClass = $contentType->modelClass();
    /** @var Model $entry */
    $entry = new $modelClass(['name' => 'CLI Row']);
    $entry->save();

    $exportJob = ExportJob::create(['status' => 'pending', 'content_type_keys' => [$key]]);
    app(ExportContent::class)($exportJob);

    $path = Storage::disk((string) $exportJob->file_disk)->path((string) $exportJob->file_path);

    return [$path, $modelClass];
}

it('validates then executes the import synchronously', function () {
    [$path] = cliImportArchive('CliImportTypeA');

    $this->artisan('baobab:import', [
        'archive' => $path,
        '--strategy' => 'replace',
    ])->assertSuccessful();

    expect(ImportJob::sole()->status)->toBe('completed');
});

it('only validates and never executes with --dry-run', function () {
    [$path] = cliImportArchive('CliImportTypeB');

    $this->artisan('baobab:import', [
        'archive' => $path,
        '--strategy' => 'replace',
        '--dry-run' => true,
    ])->assertSuccessful();

    expect(ImportJob::count())->toBe(0);
});

it('fails cleanly on a missing archive file', function () {
    $this->artisan('baobab:import', ['archive' => '/no/such/archive.zip'])->assertFailed();

    expect(ImportJob::count())->toBe(0);
});

it('fails cleanly on an unknown strategy', function () {
    [$path] = cliImportArchive('CliImportTypeC');

    $this->artisan('baobab:import', ['archive' => $path, '--strategy' => 'bogus'])->assertFailed();

    expect(ImportJob::count())->toBe(0);
});
