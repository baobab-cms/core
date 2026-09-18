<?php

use Baobab\Access\Actions\GrantPermission;
use Baobab\ContentTypes\Actions\BuildContentType;
use Baobab\Exports\Jobs\RunContentExportJob;
use Baobab\Exports\Models\ExportJob;
use Baobab\Users\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

/**
 * @param  list<string>  $permissions
 */
function exportActor(array $permissions): User
{
    static $counter = 0;
    $counter++;

    $user = User::create([
        'name' => "Export Actor {$counter}",
        'email' => "export-controller-actor-{$counter}@example.com",
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

    app(BuildContentType::class)(contentTypeBlueprintJson('ExportControllerScreenType', [
        'fields' => [['key' => 'name', 'type' => 'text']],
    ]));
});

it('denies the screen without baobab.system.export.view', function () {
    $this->actingAs(exportActor([]), 'baobab')
        ->get(route('admin.system.export.index'))
        ->assertForbidden();
});

it('lists the content types available for export on the screen', function () {
    // La liste de sélection fait partie du formulaire de déclenchement,
    // gaté par `.create` (patron BackupsController) — `.view` seul ne
    // voit que l'historique des exports.
    $this->actingAs(exportActor(['baobab.system.export.view', 'baobab.system.export.create']), 'baobab')
        ->get(route('admin.system.export.index'))
        ->assertOk()
        ->assertSee('ExportControllerScreenType');
});

it('denies triggering an export without baobab.system.export.create', function () {
    $this->actingAs(exportActor(['baobab.system.export.view']), 'baobab')
        ->post(route('admin.system.export.create'), ['content_type_keys' => ['ExportControllerScreenType']])
        ->assertForbidden();
});

it('creates a pending export job and dispatches it on baobab-low, without running it inline', function () {
    Queue::fake();

    $this->actingAs(exportActor(['baobab.system.export.view', 'baobab.system.export.create']), 'baobab')
        ->post(route('admin.system.export.create'), ['content_type_keys' => ['ExportControllerScreenType']])
        ->assertRedirect(route('admin.system.export.index'));

    $exportJob = ExportJob::sole();
    expect($exportJob->status)->toBe('pending')
        ->and($exportJob->content_type_keys)->toBe(['ExportControllerScreenType']);

    Queue::assertPushedOn('baobab-low', RunContentExportJob::class, fn (RunContentExportJob $job): bool => $job->exportJobId === $exportJob->id);
});

it('rejects an empty content type selection', function () {
    $this->actingAs(exportActor(['baobab.system.export.view', 'baobab.system.export.create']), 'baobab')
        ->post(route('admin.system.export.create'), ['content_type_keys' => []])
        ->assertSessionHasErrors('content_type_keys');
});

it('denies downloading without baobab.system.export.view', function () {
    $exportJob = ExportJob::create([
        'status' => 'completed',
        'content_type_keys' => ['ExportControllerScreenType'],
        'file_disk' => 'local',
        'file_path' => 'exports/export-test.zip',
    ]);

    $this->actingAs(exportActor([]), 'baobab')
        ->get(route('admin.system.export.download', ['exportJob' => $exportJob->uuid]))
        ->assertForbidden();
});

it('404s a download for a job that is not completed', function () {
    $exportJob = ExportJob::create(['status' => 'running', 'content_type_keys' => ['ExportControllerScreenType']]);

    $this->actingAs(exportActor(['baobab.system.export.view']), 'baobab')
        ->get(route('admin.system.export.download', ['exportJob' => $exportJob->uuid]))
        ->assertNotFound();
});

it('downloads the archive of a completed job', function () {
    Storage::disk('local')->put('exports/export-download.zip', 'fake zip bytes');

    $exportJob = ExportJob::create([
        'status' => 'completed',
        'content_type_keys' => ['ExportControllerScreenType'],
        'file_disk' => 'local',
        'file_path' => 'exports/export-download.zip',
    ]);

    $this->actingAs(exportActor(['baobab.system.export.view']), 'baobab')
        ->get(route('admin.system.export.download', ['exportJob' => $exportJob->uuid]))
        ->assertOk();
});
