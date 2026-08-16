<?php

use Baobab\ContentTypes\Actions\BuildContentType;
use Baobab\ContentTypes\Actions\PublishContentEntry;
use Baobab\ContentTypes\Actions\SaveContentEntry;
use Baobab\ContentTypes\Editorial\Models\Revision;
use Baobab\ContentTypes\Models\ContentType;
use Baobab\Modules\Models\Module;
use Baobab\Modules\ModuleAutoloader;
use Baobab\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;

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
function buildRevisionCar(?int $revisionsLimit = null): array
{
    $overrides = ['fields' => [['key' => 'brand', 'type' => 'text', 'required' => true]]];

    if ($revisionsLimit !== null) {
        $overrides['revisions'] = ['limit' => $revisionsLimit];
    }

    $contentType = app(BuildContentType::class)(contentTypeBlueprintJson('RevisionEntry', $overrides));

    $module = Module::findOrFail($contentType->module_id);
    app(ModuleAutoloader::class)->registerFor($module);

    /** @var class-string<Model> $modelClass */
    $modelClass = $contentType->modelClass();

    return [$contentType->fresh(), $modelClass];
}

function revisionActor(): User
{
    static $counter = 0;
    $counter++;

    return User::create([
        'name' => "Revision Actor {$counter}",
        'email' => "revision-actor-{$counter}@example.com",
        'password' => 'secret',
    ]);
}

it('captures a manual revision on every effective save via SaveContentEntry', function () {
    $type = buildRevisionCar()[0];
    $actor = revisionActor();

    $entry = app(SaveContentEntry::class)($type, ['brand' => 'Renault'], $actor);
    app(SaveContentEntry::class)($type, ['brand' => 'Peugeot'], $actor, $entry);

    $revisions = Revision::where('revisionable_type', $entry->getMorphClass())
        ->where('revisionable_id', $entry->id)
        ->where('type', 'manual')
        ->orderBy('id')
        ->get();

    expect($revisions)->toHaveCount(2)
        ->and($revisions[0]->snapshot['brand'])->toBe('Renault')
        ->and($revisions[1]->snapshot['brand'])->toBe('Peugeot')
        ->and($revisions[1]->author_id)->toBe($actor->id);
});

it('captures a manual revision when a publication transition fires', function () {
    [$type, $modelClass] = buildRevisionCar();
    $entry = $modelClass::create(['brand' => 'Fiat'])->fresh();

    app(PublishContentEntry::class)($type, $entry);

    $revision = Revision::where('revisionable_type', $entry->getMorphClass())
        ->where('revisionable_id', $entry->id)
        ->where('type', 'manual')
        ->latest('id')
        ->first();

    expect($revision)->not->toBeNull()
        ->and($revision->summary)->toBe('Publication')
        ->and($revision->snapshot['status'])->toBe('published');
});

it('excludes blueprint-declared fields from the snapshot', function () {
    // Clé distincte de contentTypeBlueprintJson('RevisionsEntry') par défaut ('Car') : ce type déclare
    // un champ en plus (view_count) — réutiliser 'Car' collisionnerait avec
    // la classe Modules\Car\Models\Car déjà chargée en mémoire par un autre
    // test de ce fichier (PHP ne recharge jamais une classe déjà définie,
    // même si le fichier généré sur disque change entre deux tests).
    $contentType = app(BuildContentType::class)(contentTypeBlueprintJson('Truck', [
        'label' => ['singular' => 'Camion', 'plural' => 'Camions'],
        'fields' => [
            ['key' => 'brand', 'type' => 'text', 'required' => true],
            ['key' => 'view_count', 'type' => 'integer'],
        ],
        'revisions' => ['except' => ['view_count']],
    ]));
    $module = Module::findOrFail($contentType->module_id);
    app(ModuleAutoloader::class)->registerFor($module);

    $entry = app(SaveContentEntry::class)($contentType->fresh(), ['brand' => 'Renault', 'view_count' => 42], revisionActor());

    $revision = Revision::where('revisionable_type', $entry->getMorphClass())->where('revisionable_id', $entry->id)->sole();

    expect($revision->snapshot)->toHaveKey('brand')
        ->not->toHaveKey('view_count');
});

it('disables revisions entirely when the type sets limit to 0', function () {
    $type = buildRevisionCar(revisionsLimit: 0)[0];
    $actor = revisionActor();

    app(SaveContentEntry::class)($type, ['brand' => 'Renault'], $actor);

    expect(Revision::count())->toBe(0);
});

it('purges old manual revisions beyond the quota, keeping the most recent', function () {
    // QUEUE_CONNECTION=sync en environnement de test (phpunit.xml) — le job de purge s'exécute immédiatement.
    $type = buildRevisionCar(revisionsLimit: 2)[0];
    $actor = revisionActor();

    $entry = app(SaveContentEntry::class)($type, ['brand' => 'v1'], $actor);
    app(SaveContentEntry::class)($type, ['brand' => 'v2'], $actor, $entry);
    app(SaveContentEntry::class)($type, ['brand' => 'v3'], $actor, $entry);

    $remaining = Revision::where('revisionable_type', $entry->getMorphClass())
        ->where('revisionable_id', $entry->id)
        ->where('type', 'manual')
        ->orderBy('id')
        ->pluck('snapshot')
        ->map(fn ($snapshot) => $snapshot['brand'])
        ->all();

    expect($remaining)->toBe(['v2', 'v3']);
});

it('never purges pre_restore revisions regardless of quota', function () {
    $type = buildRevisionCar(revisionsLimit: 1)[0];
    $actor = revisionActor();

    $entry = app(SaveContentEntry::class)($type, ['brand' => 'v1'], $actor);

    Revision::create([
        'revisionable_type' => $entry->getMorphClass(),
        'revisionable_id' => $entry->id,
        'type' => 'pre_restore',
        'snapshot' => ['brand' => 'old'],
    ]);

    app(SaveContentEntry::class)($type, ['brand' => 'v2'], $actor, $entry);
    app(SaveContentEntry::class)($type, ['brand' => 'v3'], $actor, $entry);

    expect(Revision::where('type', 'pre_restore')->count())->toBe(1);
});
