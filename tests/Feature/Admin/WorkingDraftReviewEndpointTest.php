<?php

use Baobab\Access\Actions\GrantPermission;
use Baobab\ContentTypes\Actions\BuildContentType;
use Baobab\ContentTypes\Actions\PublishContentEntry;
use Baobab\ContentTypes\Actions\SaveWorkingDraftEntry;
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
function reviewEndpointCarType(): array
{
    $contentType = app(BuildContentType::class)(contentTypeBlueprintJson('WorkingDraftReviewEndpointEntry', [
        'workflow' => true,
        'fields' => [['key' => 'brand', 'type' => 'text', 'required' => true]],
    ]));

    $module = Module::findOrFail($contentType->module_id);
    app(ModuleAutoloader::class)->registerFor($module);

    /** @var class-string<Model> $modelClass */
    $modelClass = $contentType->modelClass();

    return [$contentType->fresh(), $modelClass];
}

/**
 * @param  list<string>  $permissions
 */
function reviewEndpointActor(array $permissions): User
{
    static $counter = 0;
    $counter++;

    $user = User::create([
        'name' => "Review Endpoint Actor {$counter}",
        'email' => "review-endpoint-actor-{$counter}@example.com",
        'password' => 'secret',
    ]);

    app(GrantPermission::class)($user, 'baobab.admin.access');

    foreach ($permissions as $permission) {
        app(GrantPermission::class)($user, $permission);
    }

    return $user;
}

function workingDraftReviewUrl(string $action, string $entry): string
{
    return route("admin.content.working-draft.{$action}", ['contentType' => 'working-draft-review-endpoint-entries', 'entry' => $entry]);
}

/**
 * Contenu publié avec un working draft pas encore soumis à validation.
 *
 * @return array{0: ContentType, 1: Model, 2: User}
 */
function draftedReviewCar(): array
{
    [$type, $modelClass] = reviewEndpointCarType();
    $author = reviewEndpointActor(['content.working_draft_review_endpoint_entry.update']);

    $entry = new $modelClass(['brand' => 'Renault']);
    $entry->author_id = $author->id;
    $entry->save();
    $entry = $entry->fresh();

    app(PublishContentEntry::class)($type, $entry);
    app(SaveWorkingDraftEntry::class)($type, $entry, ['brand' => 'Renault Draft'], $author);

    return [$type, $entry, $author];
}

/**
 * Même chose, working draft déjà soumis à validation (statut `pending`).
 *
 * @return array{0: Model, 1: User}
 */
function submittedReviewCar(): array
{
    [, $entry, $author] = draftedReviewCar();

    test()->actingAs($author, 'baobab')->post(workingDraftReviewUrl('submit', (string) $entry->id));

    return [$entry, $author];
}

it('denies submitting a working draft without the update permission', function () {
    [, $entry] = draftedReviewCar();
    $actor = reviewEndpointActor([]);

    $this->actingAs($actor, 'baobab')
        ->post(workingDraftReviewUrl('submit', (string) $entry->id))
        ->assertForbidden();
});

it('allows the own-content author to submit their working draft for review', function () {
    [, $entry, $author] = draftedReviewCar();

    $this->actingAs($author, 'baobab')
        ->post(workingDraftReviewUrl('submit', (string) $entry->id))
        ->assertRedirect()
        ->assertSessionHas('toast');

    expect(Revision::where('type', 'pending')->sole()->revisionable_id)->toBe($entry->id);
});

it('denies approving a working draft without publish_any', function () {
    [$entry] = submittedReviewCar();

    $actor = reviewEndpointActor(['content.working_draft_review_endpoint_entry.publish']);

    $this->actingAs($actor, 'baobab')
        ->post(workingDraftReviewUrl('approve', (string) $entry->id))
        ->assertForbidden();
});

it('approves a pending working draft and applies it to the live row', function () {
    [$entry] = submittedReviewCar();

    $reviewer = reviewEndpointActor(['content.working_draft_review_endpoint_entry.publish_any']);

    $this->actingAs($reviewer, 'baobab')
        ->post(workingDraftReviewUrl('approve', (string) $entry->id))
        ->assertRedirect()
        ->assertSessionHas('toast');

    $fresh = $entry->fresh();
    expect($fresh->brand)->toBe('Renault Draft')
        ->and($fresh->status)->toBe('published')
        ->and(Revision::where('type', 'pending')->count())->toBe(0);
});

it('requires a comment to reject a pending working draft', function () {
    [$entry] = submittedReviewCar();

    $reviewer = reviewEndpointActor(['content.working_draft_review_endpoint_entry.publish_any']);

    $this->actingAs($reviewer, 'baobab')
        ->post(workingDraftReviewUrl('reject', (string) $entry->id), [])
        ->assertSessionHasErrors('comment');

    expect(Revision::where('type', 'pending')->count())->toBe(1);
});

it('rejects a pending working draft back to an editable state', function () {
    [$entry] = submittedReviewCar();

    $reviewer = reviewEndpointActor(['content.working_draft_review_endpoint_entry.publish_any']);

    $this->actingAs($reviewer, 'baobab')
        ->post(workingDraftReviewUrl('reject', (string) $entry->id), ['comment' => 'Photo manquante.'])
        ->assertRedirect()
        ->assertSessionHas('toast');

    expect(Revision::where('type', 'working_draft')->sole()->snapshot['brand'])->toBe('Renault Draft')
        ->and($entry->fresh()->status)->toBe('published');
});
