<?php

use Baobab\Access\Actions\GrantPermission;
use Baobab\ContentTypes\Actions\BuildContentType;
use Baobab\ContentTypes\Models\ContentType;
use Baobab\Modules\Models\Module;
use Baobab\Modules\ModuleAutoloader;
use Baobab\Seo\Models\SeoMeta;
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
 * @param  array<string, mixed>  $overrides
 * @return array{0: ContentType, 1: class-string<Model>}
 */
function buildMetaboxCar(array $overrides = []): array
{
    $contentType = app(BuildContentType::class)(carBlueprintJson(array_replace([
        'key' => 'MetaboxCar',
        'is_addressable' => true,
        'title_field' => 'brand',
        'fields' => [['key' => 'brand', 'type' => 'text', 'required' => true]],
    ], $overrides)));

    $module = Module::findOrFail($contentType->module_id);
    app(ModuleAutoloader::class)->registerFor($module);

    /** @var class-string<Model> $modelClass */
    $modelClass = $contentType->modelClass();

    return [$contentType->fresh(), $modelClass];
}

/**
 * @param  list<string>  $permissions
 */
function metaboxActor(array $permissions): User
{
    static $counter = 0;
    $counter++;

    $user = User::create([
        'name' => "Metabox actor {$counter}",
        'email' => "metabox-actor-{$counter}@example.com",
        'password' => 'secret',
    ]);

    app(GrantPermission::class)($user, 'baobab.admin.access');

    foreach ($permissions as $permission) {
        app(GrantPermission::class)($user, $permission);
    }

    return $user;
}

it('shows the SEO metabox on the create form of an addressable content type', function () {
    buildMetaboxCar();
    $user = metaboxActor(['content.metabox_car.view', 'content.metabox_car.create']);

    $this->actingAs($user, 'baobab')
        ->get(route('admin.content.create', ['contentType' => 'metabox-cars']))
        ->assertOk()
        ->assertSee('seo[meta_title]', false);
});

it('saves the metabox fields alongside the content entry on creation', function () {
    buildMetaboxCar();
    $user = metaboxActor(['content.metabox_car.view', 'content.metabox_car.create']);

    $this->actingAs($user, 'baobab')
        ->post(route('admin.content.store', ['contentType' => 'metabox-cars']), [
            'brand' => 'Peugeot 208',
            'slug' => 'peugeot-208',
            'seo' => [
                'meta_title' => 'Custom title',
                'meta_description' => 'Custom description',
                'robots_noindex' => '1',
            ],
        ])
        ->assertRedirect(route('admin.content.index', ['contentType' => 'metabox-cars']));

    /** @var class-string<Model> $modelClass */
    $modelClass = ContentType::where('key', 'MetaboxCar')->firstOrFail()->modelClass();
    $entry = $modelClass::where('slug', 'peugeot-208')->firstOrFail();
    $meta = SeoMeta::forEntry($entry);

    expect($meta->exists)->toBeTrue()
        ->and($meta->meta_title)->toBe('Custom title')
        ->and($meta->meta_description)->toBe('Custom description')
        ->and($meta->robots_noindex)->toBeTrue()
        ->and($meta->robots_nofollow)->toBeFalse();
});

it('updates the existing metabox row on a subsequent save rather than duplicating it', function () {
    [$type, $modelClass] = buildMetaboxCar();
    $owner = metaboxActor(['content.metabox_car.view', 'content.metabox_car.create', 'content.metabox_car.update']);
    $entry = $modelClass::create(['brand' => 'Peugeot 208', 'slug' => 'peugeot-208', 'status' => 'draft', 'author_id' => $owner->id]);
    SeoMeta::forEntry($entry)->fill(['meta_title' => 'Old title'])->save();

    $this->actingAs($owner, 'baobab')
        ->put(route('admin.content.update', ['contentType' => 'metabox-cars', 'entry' => $entry->getKey()]), [
            'brand' => 'Peugeot 208',
            'slug' => 'peugeot-208',
            'seo' => ['meta_title' => 'New title'],
        ])
        ->assertRedirect(route('admin.content.index', ['contentType' => 'metabox-cars']));

    expect(SeoMeta::query()->count())->toBe(1)
        ->and(SeoMeta::forEntry($entry->fresh() ?? $entry)->meta_title)->toBe('New title');
});

it('does not fail the content save when the submitted SEO fields are invalid', function () {
    buildMetaboxCar();
    $user = metaboxActor(['content.metabox_car.view', 'content.metabox_car.create']);

    $this->actingAs($user, 'baobab')
        ->post(route('admin.content.store', ['contentType' => 'metabox-cars']), [
            'brand' => 'Peugeot 208',
            'slug' => 'peugeot-208',
            'seo' => ['canonical_url' => 'not-a-url'],
        ])
        ->assertRedirect(route('admin.content.index', ['contentType' => 'metabox-cars']));

    /** @var class-string<Model> $modelClass */
    $modelClass = ContentType::where('key', 'MetaboxCar')->firstOrFail()->modelClass();
    expect($modelClass::where('slug', 'peugeot-208')->exists())->toBeTrue()
        ->and(SeoMeta::query()->count())->toBe(0);
});

it('omits the SEO metabox for a non-addressable content type', function () {
    $contentType = app(BuildContentType::class)((string) json_encode([
        'key' => 'MetaboxNote',
        'label' => ['singular' => 'Note', 'plural' => 'Notes'],
        'fields' => [['key' => 'body', 'type' => 'text', 'required' => true]],
    ]));
    $module = Module::findOrFail($contentType->module_id);
    app(ModuleAutoloader::class)->registerFor($module);

    $user = metaboxActor(['content.metabox_note.view', 'content.metabox_note.create']);

    $this->actingAs($user, 'baobab')
        ->get(route('admin.content.create', ['contentType' => 'metabox-notes']))
        ->assertOk()
        ->assertDontSee('seo[meta_title]', false);
});
