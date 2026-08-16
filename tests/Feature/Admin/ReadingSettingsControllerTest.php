<?php

use Baobab\Access\Actions\GrantPermission;
use Baobab\Audit\Models\AuditEntry;
use Baobab\ContentTypes\Actions\BuildContentType;
use Baobab\ContentTypes\Models\ContentType;
use Baobab\Modules\Models\Module;
use Baobab\Modules\ModuleAutoloader;
use Baobab\Rendering\Models\ReadingSetting;
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
 * @param  list<string>  $permissions
 */
function readingActor(array $permissions): User
{
    static $counter = 0;
    $counter++;

    $user = User::create([
        'name' => "Reading Actor {$counter}",
        'email' => "reading-actor-{$counter}@example.com",
        'password' => 'secret',
    ]);

    app(GrantPermission::class)($user, 'baobab.admin.access');

    foreach ($permissions as $permission) {
        app(GrantPermission::class)($user, $permission);
    }

    return $user;
}

/**
 * @return array{0: ContentType, 1: class-string<Model>}
 */
function buildReadingCarType(): array
{
    $contentType = app(BuildContentType::class)(contentTypeBlueprintJson('ReadingPage', [
        'is_addressable' => true,
        'title_field' => 'brand',
        'fields' => [['key' => 'brand', 'type' => 'text', 'required' => true]],
    ]));

    $module = Module::findOrFail($contentType->module_id);
    app(ModuleAutoloader::class)->registerFor($module);

    /** @var class-string<Model> $modelClass */
    $modelClass = $contentType->modelClass();

    return [$contentType->fresh(), $modelClass];
}

it('denies the reading screen without baobab.system.reading.manage', function () {
    $actor = readingActor([]);

    $this->actingAs($actor, 'baobab')
        ->get(route('admin.reading.index'))
        ->assertForbidden();
});

it('shows the reading screen to an actor with baobab.system.reading.manage', function () {
    $actor = readingActor(['baobab.system.reading.manage']);

    $this->actingAs($actor, 'baobab')
        ->get(route('admin.reading.index'))
        ->assertOk();
});

it('saves the static page mode and audits the change', function () {
    [, $modelClass] = buildReadingCarType();
    $entry = $modelClass::create(['brand' => 'Peugeot 208', 'slug' => 'peugeot-208', 'status' => 'published']);
    $actor = readingActor(['baobab.system.reading.manage']);

    $this->actingAs($actor, 'baobab')
        ->post(route('admin.reading.update'), [
            'mode' => 'static_page',
            'page_content_type_key' => 'ReadingPage',
            'page_entry_id' => $entry->id,
        ])
        ->assertRedirect(route('admin.reading.index'))
        ->assertSessionHas('toast');

    expect(ReadingSetting::current()->mode)->toBe('static_page')
        ->and(ReadingSetting::current()->page_entry_id)->toBe($entry->id);
    expect(AuditEntry::where('action', 'reading.updated')->exists())->toBeTrue();
});

it('saves the latest posts mode', function () {
    buildReadingCarType();
    $actor = readingActor(['baobab.system.reading.manage']);

    $this->actingAs($actor, 'baobab')
        ->post(route('admin.reading.update'), [
            'mode' => 'latest_posts',
            'posts_content_type_key' => 'ReadingPage',
        ])
        ->assertRedirect(route('admin.reading.index'));

    expect(ReadingSetting::current()->mode)->toBe('latest_posts')
        ->and(ReadingSetting::current()->posts_content_type_key)->toBe('ReadingPage');
});

it('resets to the theme default when the mode is cleared', function () {
    buildReadingCarType();
    $actor = readingActor(['baobab.system.reading.manage']);
    ReadingSetting::create(['mode' => 'latest_posts', 'posts_content_type_key' => 'ReadingPage']);

    $this->actingAs($actor, 'baobab')
        ->post(route('admin.reading.update'), ['mode' => ''])
        ->assertRedirect(route('admin.reading.index'));

    expect(ReadingSetting::current()->mode)->toBeNull()
        ->and(ReadingSetting::current()->posts_content_type_key)->toBeNull();
});

it('rejects a static page pointing at an unpublished entry', function () {
    [, $modelClass] = buildReadingCarType();
    $entry = $modelClass::create(['brand' => 'Draft', 'slug' => 'draft', 'status' => 'draft']);
    $actor = readingActor(['baobab.system.reading.manage']);

    $this->actingAs($actor, 'baobab')
        ->post(route('admin.reading.update'), [
            'mode' => 'static_page',
            'page_content_type_key' => 'ReadingPage',
            'page_entry_id' => $entry->id,
        ])
        ->assertSessionHasErrors('page_entry_id');
});

it('rejects a content type key that is not addressable', function () {
    $actor = readingActor(['baobab.system.reading.manage']);

    $this->actingAs($actor, 'baobab')
        ->post(route('admin.reading.update'), [
            'mode' => 'latest_posts',
            'posts_content_type_key' => 'unknown-type',
        ])
        ->assertSessionHasErrors('posts_content_type_key');
});
