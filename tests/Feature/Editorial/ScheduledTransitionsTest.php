<?php

use Baobab\ContentTypes\Actions\ArchiveDueContentEntries;
use Baobab\ContentTypes\Actions\BuildContentType;
use Baobab\ContentTypes\Actions\PublishScheduledContentEntries;
use Baobab\ContentTypes\Models\ContentType;
use Baobab\Modules\Models\Module;
use Baobab\Modules\ModuleAutoloader;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    File::deleteDirectory(generatedModulesPath());
    config(['baobab.content_types.modules_path' => generatedModulesPath()]);
    config(['baobab.modules.paths' => ['local' => [generatedModulesPath().'/*']]]);
});

afterEach(function () {
    File::deleteDirectory(generatedModulesPath());
});

/**
 * @return array{0: ContentType, 1: class-string<Model>}
 */
function buildScheduledCar(bool $unpublishAt = true): array
{
    $contentType = app(BuildContentType::class)(contentTypeBlueprintJson('ScheduledTransitionsEntry', [
        'unpublish_at' => $unpublishAt,
        'fields' => [['key' => 'brand', 'type' => 'text', 'required' => true]],
    ]));

    $module = Module::findOrFail($contentType->module_id);
    app(ModuleAutoloader::class)->registerFor($module);

    /** @var class-string<Model> $modelClass */
    $modelClass = $contentType->modelClass();

    return [$contentType->fresh(), $modelClass];
}

it('publishes scheduled entries whose published_at is due, leaves the rest alone', function () {
    [, $modelClass] = buildScheduledCar();

    $due = $modelClass::create(['brand' => 'Due', 'status' => 'scheduled', 'published_at' => now()->subMinute()]);
    $notYet = $modelClass::create(['brand' => 'Not yet', 'status' => 'scheduled', 'published_at' => now()->addDay()]);

    $count = app(PublishScheduledContentEntries::class)();

    expect($count)->toBe(1)
        ->and($due->fresh()->status)->toBe('published')
        ->and($notYet->fresh()->status)->toBe('scheduled');
});

it('archives published entries whose unpublish_at is due', function () {
    [, $modelClass] = buildScheduledCar();

    $due = $modelClass::create(['brand' => 'Due', 'status' => 'published', 'unpublish_at' => now()->subMinute()]);
    $notYet = $modelClass::create(['brand' => 'Not yet', 'status' => 'published', 'unpublish_at' => now()->addDay()]);
    $noDate = $modelClass::create(['brand' => 'No date', 'status' => 'published']);

    $count = app(ArchiveDueContentEntries::class)();

    expect($count)->toBe(1)
        ->and($due->fresh()->status)->toBe('archived')
        ->and($notYet->fresh()->status)->toBe('published')
        ->and($noDate->fresh()->status)->toBe('published');
});

it('skips a type without the unpublish_at column instead of crashing', function () {
    [, $modelClass] = buildScheduledCar(unpublishAt: false);

    $modelClass::create(['brand' => 'No column', 'status' => 'published']);

    $count = app(ArchiveDueContentEntries::class)();

    expect($count)->toBe(0);
});

it('treats unpublishAtColumnExists as false for a type built before the column existed, even though the blueprint says true', function () {
    [$contentType, $modelClass] = buildScheduledCar(unpublishAt: true);

    // Simule un Content Type déjà construit avant l'introduction de la colonne (suivi n° 37) :
    // le blueprint persisté dit "true", mais la table réelle n'a jamais reçu la colonne.
    Schema::table($contentType->table_name, fn ($table) => $table->dropColumn('unpublish_at'));

    expect($contentType->fresh()->unpublishAtColumnExists())->toBeFalse();

    $modelClass::create(['brand' => 'Legacy', 'status' => 'published']);

    // Ne doit pas tenter de lire/filtrer une colonne inexistante.
    expect(app(ArchiveDueContentEntries::class)())->toBe(0);
});
