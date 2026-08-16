<?php

use Baobab\ContentTypes\Actions\AcquireOrRefreshContentLock;
use Baobab\ContentTypes\Actions\BuildContentType;
use Baobab\ContentTypes\Actions\ReleaseContentLock;
use Baobab\ContentTypes\Actions\TakeOverContentLock;
use Baobab\ContentTypes\Editorial\Models\ContentLock;
use Baobab\ContentTypes\Exceptions\ContentLockedException;
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
function buildLockCar(): array
{
    $contentType = app(BuildContentType::class)(contentTypeBlueprintJson('ContentLockEntry', [
        'fields' => [['key' => 'brand', 'type' => 'text', 'required' => true]],
    ]));

    $module = Module::findOrFail($contentType->module_id);
    app(ModuleAutoloader::class)->registerFor($module);

    /** @var class-string<Model> $modelClass */
    $modelClass = $contentType->modelClass();

    return [$contentType->fresh(), $modelClass];
}

function lockActor(): User
{
    static $counter = 0;
    $counter++;

    return User::create([
        'name' => "Lock Actor {$counter}",
        'email' => "lock-actor-{$counter}@example.com",
        'password' => 'secret',
    ]);
}

it('acquires a free lock', function () {
    [, $modelClass] = buildLockCar();
    $entry = $modelClass::create(['brand' => 'Renault']);
    $actor = lockActor();

    $lock = app(AcquireOrRefreshContentLock::class)($entry, $actor);

    expect($lock->user_id)->toBe($actor->id);
});

it('heartbeats (touches) a lock already held by the same user, without throwing', function () {
    [, $modelClass] = buildLockCar();
    $entry = $modelClass::create(['brand' => 'Renault']);
    $actor = lockActor();

    $first = app(AcquireOrRefreshContentLock::class)($entry, $actor);
    $originalUpdatedAt = $first->updated_at;

    test()->travel(1)->minutes();
    $second = app(AcquireOrRefreshContentLock::class)($entry, $actor);

    expect($second->id)->toBe($first->id)
        ->and($second->updated_at->gt($originalUpdatedAt))->toBeTrue()
        ->and(ContentLock::count())->toBe(1);
});

it('refuses to acquire a lock held by another user, still fresh', function () {
    [, $modelClass] = buildLockCar();
    $entry = $modelClass::create(['brand' => 'Renault']);
    $alice = lockActor();
    $bob = lockActor();

    app(AcquireOrRefreshContentLock::class)($entry, $alice);

    app(AcquireOrRefreshContentLock::class)($entry, $bob);
})->throws(ContentLockedException::class);

it('silently reacquires a lock that expired without a heartbeat', function () {
    config(['baobab.content.lock_expiry_seconds' => 120]);

    [, $modelClass] = buildLockCar();
    $entry = $modelClass::create(['brand' => 'Renault']);
    $alice = lockActor();
    $bob = lockActor();

    app(AcquireOrRefreshContentLock::class)($entry, $alice);

    test()->travel(3)->minutes();

    $lock = app(AcquireOrRefreshContentLock::class)($entry, $bob);

    expect($lock->user_id)->toBe($bob->id)
        ->and(ContentLock::count())->toBe(1);
});

it('lets a holder release their own lock', function () {
    [, $modelClass] = buildLockCar();
    $entry = $modelClass::create(['brand' => 'Renault']);
    $actor = lockActor();

    app(AcquireOrRefreshContentLock::class)($entry, $actor);
    app(ReleaseContentLock::class)($entry, $actor);

    expect(ContentLock::count())->toBe(0);
});

it('does not let a release call remove someone else\'s lock', function () {
    [, $modelClass] = buildLockCar();
    $entry = $modelClass::create(['brand' => 'Renault']);
    $alice = lockActor();
    $bob = lockActor();

    app(AcquireOrRefreshContentLock::class)($entry, $alice);
    app(ReleaseContentLock::class)($entry, $bob);

    expect(ContentLock::count())->toBe(1)
        ->and(ContentLock::sole()->user_id)->toBe($alice->id);
});

it('lets a take-over seize a lock still held by someone else', function () {
    [, $modelClass] = buildLockCar();
    $entry = $modelClass::create(['brand' => 'Renault']);
    $alice = lockActor();
    $bob = lockActor();

    app(AcquireOrRefreshContentLock::class)($entry, $alice);
    app(TakeOverContentLock::class)($entry, $bob);

    expect(ContentLock::count())->toBe(1)
        ->and(ContentLock::sole()->user_id)->toBe($bob->id);

    // Alice a bien perdu la main : son prochain heartbeat échoue.
    app(AcquireOrRefreshContentLock::class)($entry, $alice);
})->throws(ContentLockedException::class);
