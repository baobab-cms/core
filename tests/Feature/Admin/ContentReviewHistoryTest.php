<?php

use Baobab\Access\Actions\GrantPermission;
use Baobab\ContentTypes\Actions\BuildContentType;
use Baobab\ContentTypes\Actions\RejectContentEntry;
use Baobab\ContentTypes\Actions\SubmitContentEntry;
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
function reviewHistoryCarType(): array
{
    $contentType = app(BuildContentType::class)(contentTypeBlueprintJson('ContentReviewHistoryEntry', [
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
function reviewHistoryActor(array $permissions): User
{
    static $counter = 0;
    $counter++;

    $user = User::create([
        'name' => "Review History Actor {$counter}",
        'email' => "review-history-actor-{$counter}@example.com",
        'password' => 'secret',
    ]);

    app(GrantPermission::class)($user, 'baobab.admin.access');

    foreach ($permissions as $permission) {
        app(GrantPermission::class)($user, $permission);
    }

    return $user;
}

it('shows the reject comment in the content form\'s review history thread', function () {
    [$type, $modelClass] = reviewHistoryCarType();
    $actor = reviewHistoryActor(['content.content_review_history_entry.update']);
    $entry = new $modelClass(['brand' => 'Renault']);
    $entry->author_id = $actor->id;
    $entry->save();
    $entry = $entry->fresh();

    app(SubmitContentEntry::class)($type, $entry);
    app(RejectContentEntry::class)($type, $entry, 'Photo manquante.');

    $this->actingAs($actor, 'baobab')
        ->get(route('admin.content.edit', ['contentType' => 'content-review-history-entries', 'entry' => $entry->id]))
        ->assertOk()
        ->assertSee('Photo manquante.', false);
});

it('hides the review history section when there is no history yet', function () {
    [, $modelClass] = reviewHistoryCarType();
    $actor = reviewHistoryActor(['content.content_review_history_entry.update']);
    $entry = new $modelClass(['brand' => 'Renault']);
    $entry->author_id = $actor->id;
    $entry->save();

    $this->actingAs($actor, 'baobab')
        ->get(route('admin.content.edit', ['contentType' => 'content-review-history-entries', 'entry' => $entry->id]))
        ->assertOk()
        ->assertDontSee('Photo manquante.', false);
});
