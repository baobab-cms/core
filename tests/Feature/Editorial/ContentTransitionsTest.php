<?php

use Baobab\Audit\Models\AuditEntry;
use Baobab\ContentTypes\Actions\ApproveContentEntry;
use Baobab\ContentTypes\Actions\ArchiveContentEntry;
use Baobab\ContentTypes\Actions\BuildContentType;
use Baobab\ContentTypes\Actions\PublishContentEntry;
use Baobab\ContentTypes\Actions\RejectContentEntry;
use Baobab\ContentTypes\Actions\RestoreArchivedContentEntry;
use Baobab\ContentTypes\Actions\ScheduleContentEntry;
use Baobab\ContentTypes\Actions\SubmitContentEntry;
use Baobab\ContentTypes\Actions\UnpublishContentEntry;
use Baobab\ContentTypes\Exceptions\InvalidContentTransitionException;
use Baobab\ContentTypes\Models\ContentType;
use Baobab\Facades\Hook;
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
function buildEditorialCar(bool $workflow = true): array
{
    $contentType = app(BuildContentType::class)(carBlueprintJson([
        'workflow' => $workflow,
        'fields' => [['key' => 'brand', 'type' => 'text', 'required' => true]],
    ]));

    $module = Module::findOrFail($contentType->module_id);
    app(ModuleAutoloader::class)->registerFor($module);

    /** @var class-string<Model> $modelClass */
    $modelClass = $contentType->modelClass();

    return [$contentType->fresh(), $modelClass];
}

function editorialActor(): User
{
    static $counter = 0;
    $counter++;

    return User::create([
        'name' => "Editorial Actor {$counter}",
        'email' => "editorial-actor-{$counter}@example.com",
        'password' => 'secret',
    ]);
}

it('runs submit → approve immediately → published, with audit and hooks', function () {
    [$type, $modelClass] = buildEditorialCar();
    $entry = $modelClass::create(['brand' => 'Renault'])->fresh();
    $actor = editorialActor();

    app(SubmitContentEntry::class)($type, $entry, $actor);
    expect($entry->fresh()->status)->toBe('pending');

    app(ApproveContentEntry::class)($type, $entry, null, $actor);
    $entry = $entry->fresh();

    expect($entry->status)->toBe('published')
        ->and($entry->published_at)->not->toBeNull();

    expect(AuditEntry::where('action', 'content.submitted')->exists())->toBeTrue()
        ->and(AuditEntry::where('action', 'content.approved')->exists())->toBeTrue();
});

it('runs submit → approve with a future date → scheduled', function () {
    [$type, $modelClass] = buildEditorialCar();
    $entry = $modelClass::create(['brand' => 'Peugeot'])->fresh();

    app(SubmitContentEntry::class)($type, $entry);
    $future = now()->addDays(3);
    app(ApproveContentEntry::class)($type, $entry, $future);

    $entry = $entry->fresh();
    expect($entry->status)->toBe('scheduled')
        ->and($entry->published_at->format('Y-m-d H:i:s'))->toBe($future->format('Y-m-d H:i:s'));
});

it('runs submit → reject with a comment → back to draft, comment kept in the audit entry', function () {
    [$type, $modelClass] = buildEditorialCar();
    $entry = $modelClass::create(['brand' => 'Citroën'])->fresh();

    app(SubmitContentEntry::class)($type, $entry);
    app(RejectContentEntry::class)($type, $entry, 'Photo manquante.');

    expect($entry->fresh()->status)->toBe('draft');

    $audit = AuditEntry::where('action', 'content.rejected')->first();
    expect($audit->data['comment'])->toBe('Photo manquante.');
});

it('publishes directly from draft without going through pending', function () {
    [$type, $modelClass] = buildEditorialCar();
    $entry = $modelClass::create(['brand' => 'Fiat'])->fresh();

    app(PublishContentEntry::class)($type, $entry);

    expect($entry->fresh()->status)->toBe('published');
});

it('clears published_at on unpublish', function () {
    [$type, $modelClass] = buildEditorialCar();
    $entry = $modelClass::create(['brand' => 'Volvo'])->fresh();

    app(PublishContentEntry::class)($type, $entry);
    app(UnpublishContentEntry::class)($type, $entry);

    $entry = $entry->fresh();
    expect($entry->status)->toBe('draft')
        ->and($entry->published_at)->toBeNull();
});

it('archives and restores back to draft', function () {
    [$type, $modelClass] = buildEditorialCar();
    $entry = $modelClass::create(['brand' => 'Skoda'])->fresh();

    app(ArchiveContentEntry::class)($type, $entry);
    expect($entry->fresh()->status)->toBe('archived');

    app(RestoreArchivedContentEntry::class)($type, $entry);
    expect($entry->fresh()->status)->toBe('draft');
});

it('reprograms a scheduled item and force-publishes it directly', function () {
    [$type, $modelClass] = buildEditorialCar();
    $entry = $modelClass::create(['brand' => 'Seat'])->fresh();

    app(ScheduleContentEntry::class)($type, $entry, now()->addDay());
    expect($entry->fresh()->status)->toBe('scheduled');

    app(ScheduleContentEntry::class)($type, $entry, now()->addWeek());
    expect($entry->fresh()->status)->toBe('scheduled');

    app(PublishContentEntry::class)($type, $entry);
    expect($entry->fresh()->status)->toBe('published');
});

it('rejects an illegal transition (archived cannot be published directly)', function () {
    [$type, $modelClass] = buildEditorialCar();
    $entry = $modelClass::create(['brand' => 'Opel'])->fresh();

    app(ArchiveContentEntry::class)($type, $entry);

    app(PublishContentEntry::class)($type, $entry);
})->throws(InvalidContentTransitionException::class);

it('fires the generic baobab.content.transitioned hook with contentType/entry/from/to/actor', function () {
    [$type, $modelClass] = buildEditorialCar();
    $entry = $modelClass::create(['brand' => 'Audi'])->fresh();
    $actor = editorialActor();

    $captured = null;
    Hook::listen('baobab.content.transitioned', function ($ct, $en, $from, $to, $who) use (&$captured): void {
        $captured = [$ct->key, $en->id, $from, $to, $who?->id];
    });

    app(PublishContentEntry::class)($type, $entry, $actor);

    expect($captured)->toBe(['Car', $entry->id, 'draft', 'published', $actor->id]);
});

it('rejects submit when the type has not opted into the workflow', function () {
    [$type, $modelClass] = buildEditorialCar(workflow: false);
    $entry = $modelClass::create(['brand' => 'Mini'])->fresh();

    app(SubmitContentEntry::class)($type, $entry);
})->throws(InvalidContentTransitionException::class);
