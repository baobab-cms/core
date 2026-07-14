<?php

use Baobab\Access\Actions\GrantPermission;
use Baobab\ContentTypes\Actions\BuildContentType;
use Baobab\ContentTypes\Actions\PublishContentEntry;
use Baobab\ContentTypes\Actions\SaveWorkingDraftEntry;
use Baobab\ContentTypes\Actions\SubmitContentEntry;
use Baobab\ContentTypes\Actions\SubmitWorkingDraftForReview;
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
function queueCarType(): array
{
    $contentType = app(BuildContentType::class)(carBlueprintJson([
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
function queueActor(array $permissions): User
{
    static $counter = 0;
    $counter++;

    $user = User::create([
        'name' => "Queue Actor {$counter}",
        'email' => "queue-actor-{$counter}@example.com",
        'password' => 'secret',
    ]);

    app(GrantPermission::class)($user, 'baobab.admin.access');

    foreach ($permissions as $permission) {
        app(GrantPermission::class)($user, $permission);
    }

    return $user;
}

it('lists native pending submissions for an actor with publish_any', function () {
    [$type, $modelClass] = queueCarType();
    $entry = $modelClass::create(['brand' => 'Renault'])->fresh();
    app(SubmitContentEntry::class)($type, $entry);

    $actor = queueActor(['content.car.publish_any']);

    $this->actingAs($actor, 'baobab')
        ->get(route('admin.review.index'))
        ->assertOk()
        ->assertSee('Renault', false);
});

it('lists pending working drafts of already published content', function () {
    [$type, $modelClass] = queueCarType();
    $entry = $modelClass::create(['brand' => 'Renault'])->fresh();
    app(PublishContentEntry::class)($type, $entry);
    $author = queueActor(['content.car.update']);
    app(SaveWorkingDraftEntry::class)($type, $entry, ['brand' => 'Renault Draft'], $author);
    app(SubmitWorkingDraftForReview::class)($type, Revision::where('type', 'working_draft')->sole());

    $reviewer = queueActor(['content.car.publish_any']);

    $this->actingAs($reviewer, 'baobab')
        ->get(route('admin.review.index'))
        ->assertOk()
        ->assertSee('Renault Draft', false);
});

it('hides a type\'s pending queue from an actor without publish_any on it', function () {
    [$type, $modelClass] = queueCarType();
    $entry = $modelClass::create(['brand' => 'Renault'])->fresh();
    app(SubmitContentEntry::class)($type, $entry);

    $actor = queueActor(['content.car.update']);

    $this->actingAs($actor, 'baobab')
        ->get(route('admin.review.index'))
        ->assertOk()
        ->assertDontSee('Renault', false);
});

it('filters the queue by content type', function () {
    [$type, $modelClass] = queueCarType();
    $entry = $modelClass::create(['brand' => 'Renault'])->fresh();
    app(SubmitContentEntry::class)($type, $entry);

    $actor = queueActor(['content.car.publish_any']);

    $this->actingAs($actor, 'baobab')
        ->get(route('admin.review.index', ['type' => 'bikes']))
        ->assertOk()
        ->assertDontSee('Renault', false);
});
