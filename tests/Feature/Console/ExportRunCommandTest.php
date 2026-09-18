<?php

use Baobab\ContentTypes\Actions\BuildContentType;
use Baobab\Exports\Models\ExportJob;
use Baobab\Modules\Models\Module;
use Baobab\Modules\ModuleAutoloader;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
    File::deleteDirectory(generatedModulesPath());
    config(['baobab.content_types.modules_path' => generatedModulesPath()]);
    config(['baobab.modules.paths' => ['local' => [generatedModulesPath().'/*']]]);

    $contentType = app(BuildContentType::class)(contentTypeBlueprintJson('ExportCliType', [
        'fields' => [['key' => 'name', 'type' => 'text']],
    ]));

    app(ModuleAutoloader::class)->registerFor(Module::findOrFail($contentType->module_id));
});

afterEach(function (): void {
    File::deleteDirectory(generatedModulesPath());
});

it('exports synchronously and reports the archive path', function () {
    $this->artisan('baobab:export', ['--types' => 'ExportCliType'])
        ->assertSuccessful();

    $exportJob = ExportJob::sole();
    expect($exportJob->status)->toBe('completed');
});

it('fails cleanly without --types', function () {
    $this->artisan('baobab:export')->assertFailed();

    expect(ExportJob::count())->toBe(0);
});

it('fails cleanly on an unknown content type key, without creating a job', function () {
    $this->artisan('baobab:export', ['--types' => 'GhostType'])->assertFailed();

    expect(ExportJob::count())->toBe(0);
});
