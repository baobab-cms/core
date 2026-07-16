<?php

use Baobab\Access\Actions\GrantPermission;
use Baobab\ContentTypes\Actions\ApproveContentEntry;
use Baobab\ContentTypes\Actions\ApproveWorkingDraftReview;
use Baobab\ContentTypes\Actions\BuildContentType;
use Baobab\ContentTypes\Actions\PublishContentEntry;
use Baobab\ContentTypes\Actions\RejectContentEntry;
use Baobab\ContentTypes\Actions\SaveWorkingDraftEntry;
use Baobab\ContentTypes\Actions\SubmitContentEntry;
use Baobab\ContentTypes\Actions\SubmitWorkingDraftForReview;
use Baobab\ContentTypes\Editorial\Models\Revision;
use Baobab\ContentTypes\Models\ContentType;
use Baobab\Mail\Jobs\SendQueuedMail;
use Baobab\Modules\Models\Module;
use Baobab\Modules\ModuleAutoloader;
use Baobab\Notify\Notifications\BaobabNotification;
use Baobab\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

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
function buildNotifiedCar(): array
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

function workflowNotifiedActor(): User
{
    static $counter = 0;
    $counter++;

    return User::create([
        'name' => "Workflow Notified Actor {$counter}",
        'email' => "workflow-notified-actor-{$counter}@example.com",
        'password' => 'secret',
    ]);
}

it('notifies publish_any holders on submission, on both channels', function () {
    Notification::fake();
    Queue::fake();

    [$type, $modelClass] = buildNotifiedCar();
    $reviewer = workflowNotifiedActor();
    app(GrantPermission::class)($reviewer, 'content.car.publish_any');
    $bystander = workflowNotifiedActor(); // pas de publish_any, ne doit rien recevoir

    $entry = $modelClass::create(['brand' => 'Renault'])->fresh();
    app(SubmitContentEntry::class)($type, $entry);

    Notification::assertSentTo($reviewer, BaobabNotification::class);
    Notification::assertNotSentTo($bystander, BaobabNotification::class);
    Queue::assertPushedOn('baobab', SendQueuedMail::class, fn (SendQueuedMail $job) => $job->to === $reviewer->email);
});

it('does not crash submission when no one holds publish_any yet', function () {
    [$type, $modelClass] = buildNotifiedCar();
    $entry = $modelClass::create(['brand' => 'Renault'])->fresh();

    app(SubmitContentEntry::class)($type, $entry);

    expect($entry->fresh()->status)->toBe('pending');
});

it('notifies the author on approval', function () {
    Notification::fake();
    Queue::fake();

    [$type, $modelClass] = buildNotifiedCar();
    $author = workflowNotifiedActor();
    $entry = new $modelClass(['brand' => 'Renault']);
    $entry->author_id = $author->id;
    $entry->save();
    $entry = $entry->fresh();

    app(SubmitContentEntry::class)($type, $entry);
    app(ApproveContentEntry::class)($type, $entry);

    Notification::assertSentTo($author, BaobabNotification::class);
});

it('notifies the author on rejection, with the comment in the payload', function () {
    Notification::fake();
    Queue::fake();

    [$type, $modelClass] = buildNotifiedCar();
    $author = workflowNotifiedActor();
    $entry = new $modelClass(['brand' => 'Renault']);
    $entry->author_id = $author->id;
    $entry->save();
    $entry = $entry->fresh();

    app(SubmitContentEntry::class)($type, $entry);
    app(RejectContentEntry::class)($type, $entry, 'Photo manquante.');

    Notification::assertSentTo(
        $author,
        BaobabNotification::class,
        fn (BaobabNotification $notification) => $notification->toDatabase($author)['data']['comment'] === 'Photo manquante.',
    );
});

it('notifies reviewers when a working draft is submitted for review', function () {
    Notification::fake();
    Queue::fake();

    [$type, $modelClass] = buildNotifiedCar();
    $reviewer = workflowNotifiedActor();
    app(GrantPermission::class)($reviewer, 'content.car.publish_any');
    $author = workflowNotifiedActor();

    $entry = $modelClass::create(['brand' => 'Renault'])->fresh();
    app(PublishContentEntry::class)($type, $entry);
    app(SaveWorkingDraftEntry::class)($type, $entry, ['brand' => 'Renault Draft'], $author);
    $draft = Revision::where('type', 'working_draft')->sole();
    app(SubmitWorkingDraftForReview::class)($type, $draft);

    Notification::assertSentTo($reviewer, BaobabNotification::class);
});

it('notifies the author when a working draft review is approved', function () {
    Notification::fake();
    Queue::fake();

    [$type, $modelClass] = buildNotifiedCar();
    $author = workflowNotifiedActor();
    $reviewer = workflowNotifiedActor();

    $entry = new $modelClass(['brand' => 'Renault']);
    $entry->author_id = $author->id;
    $entry->save();
    app(PublishContentEntry::class)($type, $entry->fresh());
    app(SaveWorkingDraftEntry::class)($type, $entry, ['brand' => 'Renault Draft'], $author);
    $draft = Revision::where('type', 'working_draft')->sole();
    app(SubmitWorkingDraftForReview::class)($type, $draft);

    app(ApproveWorkingDraftReview::class)($type, $entry, $draft->fresh(), $reviewer);

    Notification::assertSentTo($author, BaobabNotification::class);
});
