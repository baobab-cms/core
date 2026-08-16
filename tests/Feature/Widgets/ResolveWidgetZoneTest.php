<?php

use Baobab\ContentTypes\Actions\BuildContentType;
use Baobab\ContentTypes\Models\ContentType;
use Baobab\Facades\Hook;
use Baobab\Modules\Models\Module;
use Baobab\Modules\ModuleAutoloader;
use Baobab\Tests\Fixtures\ThrowingWidgetStub;
use Baobab\Users\Models\User;
use Baobab\Widgets\Actions\ResolveWidgetZone;
use Baobab\Widgets\Core\RecentContentsWidget;
use Baobab\Widgets\Models\WidgetInstance;
use Baobab\Widgets\WidgetRegistry;
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
function buildWidgetCarType(): array
{
    $contentType = app(BuildContentType::class)(contentTypeBlueprintJson('WidgetPost', [
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

it('returns an empty list for a zone with no instance', function () {
    expect(app(ResolveWidgetZone::class)('sidebar'))->toBe([]);
});

it('orders visible instances by order', function () {
    WidgetInstance::create(['zone_key' => 'sidebar', 'widget_key' => 'baobab.custom-html', 'settings' => ['html' => 'B'], 'order' => 1]);
    WidgetInstance::create(['zone_key' => 'sidebar', 'widget_key' => 'baobab.custom-html', 'settings' => ['html' => 'A'], 'order' => 0]);

    $items = app(ResolveWidgetZone::class)('sidebar');

    expect($items)->toHaveCount(2)
        ->and($items[0]['data']['html'])->toBe('A')
        ->and($items[1]['data']['html'])->toBe('B');
});

it('skips an inactive instance', function () {
    WidgetInstance::create(['zone_key' => 'sidebar', 'widget_key' => 'baobab.custom-html', 'settings' => ['html' => 'X'], 'order' => 0, 'is_active' => false]);

    expect(app(ResolveWidgetZone::class)('sidebar'))->toBe([]);
});

it('hides an item restricted to guests when the actor is authenticated', function () {
    WidgetInstance::create(['zone_key' => 'sidebar', 'widget_key' => 'baobab.custom-html', 'settings' => ['html' => 'X'], 'order' => 0, 'visibility' => 'guests']);

    $actor = User::create(['name' => 'Actor', 'email' => 'actor@example.com', 'password' => 'secret']);
    $this->actingAs($actor, 'baobab');

    expect(app(ResolveWidgetZone::class)('sidebar'))->toBe([]);
});

it('shows an item restricted to authenticated users only when logged in', function () {
    WidgetInstance::create(['zone_key' => 'sidebar', 'widget_key' => 'baobab.custom-html', 'settings' => ['html' => 'X'], 'order' => 0, 'visibility' => 'authenticated']);

    expect(app(ResolveWidgetZone::class)('sidebar'))->toBe([]);

    $actor = User::create(['name' => 'Actor', 'email' => 'actor2@example.com', 'password' => 'secret']);
    $this->actingAs($actor, 'baobab');

    expect(app(ResolveWidgetZone::class)('sidebar'))->toHaveCount(1);
});

it('omits an instance whose widget key is not registered', function () {
    WidgetInstance::create(['zone_key' => 'sidebar', 'widget_key' => 'unknown.widget', 'settings' => [], 'order' => 0]);

    expect(app(ResolveWidgetZone::class)('sidebar'))->toBe([]);
});

it('omits a widget whose data() throws, without failing the whole zone', function () {
    app(WidgetRegistry::class)->register(ThrowingWidgetStub::class);
    WidgetInstance::create(['zone_key' => 'sidebar', 'widget_key' => 'test.throwing', 'settings' => [], 'order' => 0]);
    WidgetInstance::create(['zone_key' => 'sidebar', 'widget_key' => 'baobab.custom-html', 'settings' => ['html' => 'ok'], 'order' => 1]);

    $items = app(ResolveWidgetZone::class)('sidebar');

    expect($items)->toHaveCount(1)
        ->and($items[0]['data']['html'])->toBe('ok');
});

it('lets a module inject items via the baobab.widgets.zone filter', function () {
    Hook::modify('baobab.widgets.zone', function (array $items, string $zoneKey) {
        $items[] = ['instance_id' => 999, 'widget_key' => 'injected', 'view' => 'baobab::widgets.error', 'data' => ['message' => 'injected']];

        return $items;
    }, priority: 5);

    $items = app(ResolveWidgetZone::class)('sidebar');

    expect(collect($items)->pluck('widget_key'))->toContain('injected');
});

it('fires baobab.widget.rendered for each rendered widget', function () {
    WidgetInstance::create(['zone_key' => 'sidebar', 'widget_key' => 'baobab.custom-html', 'settings' => ['html' => 'X'], 'order' => 0]);

    $fired = false;
    Hook::listen('baobab.widget.rendered', function () use (&$fired) {
        $fired = true;
    });

    app(ResolveWidgetZone::class)('sidebar');

    expect($fired)->toBeTrue();
});

it('caches the resolved data and reflects content changes only after invalidation', function () {
    [$contentType, $modelClass] = buildWidgetCarType();
    $entry = $modelClass::create(['brand' => 'Original', 'slug' => 'car', 'status' => 'published', 'published_at' => now()]);

    WidgetInstance::create([
        'zone_key' => 'sidebar',
        'widget_key' => RecentContentsWidget::key(),
        'settings' => ['content_type' => 'WidgetPost', 'limit' => 5, 'show_dates' => false],
        'order' => 0,
    ]);

    $items = app(ResolveWidgetZone::class)('sidebar');
    expect($items[0]['data']['items'][0]['label'])->toBe('Original');

    $entry->update(['brand' => 'Renamed']);
    Hook::action('baobab.content.saved', $contentType, $entry, false);

    $items = app(ResolveWidgetZone::class)('sidebar');
    expect($items[0]['data']['items'][0]['label'])->toBe('Renamed');
});
