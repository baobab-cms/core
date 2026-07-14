<?php

use Baobab\Audit\Models\AuditEntry;
use Baobab\ContentTypes\Actions\ApproveWorkingDraftReview;
use Baobab\ContentTypes\Actions\BuildContentType;
use Baobab\ContentTypes\Actions\PublishContentEntry;
use Baobab\ContentTypes\Actions\RejectWorkingDraftReview;
use Baobab\ContentTypes\Actions\SaveWorkingDraftEntry;
use Baobab\ContentTypes\Actions\SubmitWorkingDraftForReview;
use Baobab\ContentTypes\Editorial\Models\Revision;
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
function buildReviewableCar(bool $workflow = true): array
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

function reviewActor(): User
{
    static $counter = 0;
    $counter++;

    return User::create([
        'name' => "Review Actor {$counter}",
        'email' => "review-actor-{$counter}@example.com",
        'password' => 'secret',
    ]);
}

it('submits a working draft for review without touching the live row', function () {
    [$type, $modelClass] = buildReviewableCar();
    $entry = $modelClass::create(['brand' => 'Renault'])->fresh();
    app(PublishContentEntry::class)($type, $entry);
    $actor = reviewActor();

    app(SaveWorkingDraftEntry::class)($type, $entry, ['brand' => 'Renault Draft'], $actor);
    $draft = Revision::where('type', 'working_draft')->sole();

    app(SubmitWorkingDraftForReview::class)($type, $draft);

    expect($entry->fresh()->brand)->toBe('Renault')
        ->and($entry->fresh()->status)->toBe('published')
        ->and($draft->fresh()->type)->toBe('pending');

    expect(AuditEntry::where('action', 'content.working_draft.submitted')->exists())->toBeTrue();
});

it('rejects submitting a working draft that is already pending', function () {
    [$type, $modelClass] = buildReviewableCar();
    $entry = $modelClass::create(['brand' => 'Renault'])->fresh();
    app(PublishContentEntry::class)($type, $entry);
    $actor = reviewActor();

    app(SaveWorkingDraftEntry::class)($type, $entry, ['brand' => 'Renault Draft'], $actor);
    $draft = Revision::where('type', 'working_draft')->sole();
    app(SubmitWorkingDraftForReview::class)($type, $draft);

    app(SubmitWorkingDraftForReview::class)($type, $draft->fresh());
})->throws(InvalidContentTransitionException::class);

it('applies the pending working draft to the live row on approval, status untouched', function () {
    [$type, $modelClass] = buildReviewableCar();
    $entry = $modelClass::create(['brand' => 'Renault'])->fresh();
    app(PublishContentEntry::class)($type, $entry);
    $author = reviewActor();
    $reviewer = reviewActor();

    app(SaveWorkingDraftEntry::class)($type, $entry, ['brand' => 'Renault Draft'], $author);
    $draft = Revision::where('type', 'working_draft')->sole();
    app(SubmitWorkingDraftForReview::class)($type, $draft);

    app(ApproveWorkingDraftReview::class)($type, $entry, $draft->fresh(), $reviewer);

    $fresh = $entry->fresh();
    expect($fresh->brand)->toBe('Renault Draft')
        ->and($fresh->status)->toBe('published')
        ->and(Revision::where('type', 'pending')->count())->toBe(0)
        ->and(Revision::where('type', 'working_draft')->count())->toBe(0);

    expect(AuditEntry::where('action', 'content.working_draft.approved')->exists())->toBeTrue();
});

it('rejects approving a working draft that is not pending', function () {
    [$type, $modelClass] = buildReviewableCar();
    $entry = $modelClass::create(['brand' => 'Renault'])->fresh();
    app(PublishContentEntry::class)($type, $entry);
    $actor = reviewActor();

    app(SaveWorkingDraftEntry::class)($type, $entry, ['brand' => 'Renault Draft'], $actor);
    $draft = Revision::where('type', 'working_draft')->sole();

    app(ApproveWorkingDraftReview::class)($type, $entry, $draft, $actor);
})->throws(InvalidContentTransitionException::class);

it('reverts a rejected working draft back to an editable state, comment kept in the audit entry', function () {
    [$type, $modelClass] = buildReviewableCar();
    $entry = $modelClass::create(['brand' => 'Renault'])->fresh();
    app(PublishContentEntry::class)($type, $entry);
    $author = reviewActor();

    app(SaveWorkingDraftEntry::class)($type, $entry, ['brand' => 'Renault Draft'], $author);
    $draft = Revision::where('type', 'working_draft')->sole();
    app(SubmitWorkingDraftForReview::class)($type, $draft);

    app(RejectWorkingDraftReview::class)($type, $draft->fresh(), 'Photo manquante.');

    $fresh = $draft->fresh();
    expect($fresh->type)->toBe('working_draft')
        ->and($fresh->snapshot['brand'])->toBe('Renault Draft')
        ->and($entry->fresh()->brand)->toBe('Renault')
        ->and($entry->fresh()->status)->toBe('published');

    $audit = AuditEntry::where('action', 'content.working_draft.rejected')->first();
    expect($audit->data['comment'])->toBe('Photo manquante.');
});

it('fires the dedicated hooks for the working draft review lifecycle', function () {
    [$type, $modelClass] = buildReviewableCar();
    $entry = $modelClass::create(['brand' => 'Renault'])->fresh();
    app(PublishContentEntry::class)($type, $entry);
    $actor = reviewActor();

    $events = [];
    Hook::listen('baobab.content.working_draft.submitted', function () use (&$events): void {
        $events[] = 'submitted';
    });
    Hook::listen('baobab.content.working_draft.approved', function () use (&$events): void {
        $events[] = 'approved';
    });

    app(SaveWorkingDraftEntry::class)($type, $entry, ['brand' => 'Renault Draft'], $actor);
    $draft = Revision::where('type', 'working_draft')->sole();
    app(SubmitWorkingDraftForReview::class)($type, $draft);
    app(ApproveWorkingDraftReview::class)($type, $entry, $draft->fresh(), $actor);

    expect($events)->toBe(['submitted', 'approved']);
});
