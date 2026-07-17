<?php

use Baobab\ContentTypes\Actions\BuildContentType;
use Baobab\ContentTypes\Models\ContentType;
use Baobab\Facades\Hook;
use Baobab\Menus\Actions\ResolveMenuTree;
use Baobab\Menus\Models\Menu;
use Baobab\Menus\Models\MenuAssignment;
use Baobab\Menus\Models\MenuItem;
use Baobab\Modules\Models\Module;
use Baobab\Modules\ModuleAutoloader;
use Baobab\Themes\Models\ThemeMenuLocation;
use Baobab\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    File::deleteDirectory(generatedModulesPath());
    config(['baobab.content_types.modules_path' => generatedModulesPath()]);
    config(['baobab.modules.paths' => ['local' => [generatedModulesPath().'/*']]]);
    setCurrentPath('/');
});

afterEach(function () {
    File::deleteDirectory(generatedModulesPath());
});

function setCurrentPath(string $path): void
{
    app()->instance('request', Request::create($path));
}

/**
 * @return array{0: ContentType, 1: class-string<Model>}
 */
function buildMenuCarType(): array
{
    $contentType = app(BuildContentType::class)(carBlueprintJson([
        'key' => 'MenuCar',
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

function assignedMenu(string $locationKey = 'primary'): Menu
{
    ThemeMenuLocation::create(['key' => $locationKey, 'label' => 'Primary', 'is_active' => true]);
    $menu = Menu::create(['name' => 'Main']);
    MenuAssignment::create(['menu_id' => $menu->id, 'location_key' => $locationKey]);

    return $menu;
}

it('returns an empty tree when the location has no assignment', function () {
    expect(app(ResolveMenuTree::class)('unassigned'))->toBe([]);
});

it('resolves a content item to the entry URL and title', function () {
    [, $modelClass] = buildMenuCarType();
    $entry = $modelClass::create(['brand' => 'Peugeot 208', 'slug' => 'peugeot-208', 'status' => 'published']);
    $menu = assignedMenu();
    MenuItem::create(['menu_id' => $menu->id, 'order' => 0, 'type' => 'content', 'linkable_type' => $entry::class, 'linkable_id' => $entry->id]);

    $tree = app(ResolveMenuTree::class)('primary');

    expect($tree)->toHaveCount(1)
        ->and($tree[0]['label'])->toBe('Peugeot 208')
        ->and($tree[0]['url'])->toBe('/menu-cars/peugeot-208');
});

it('honors a label override for a content item', function () {
    [, $modelClass] = buildMenuCarType();
    $entry = $modelClass::create(['brand' => 'Peugeot 208', 'slug' => 'peugeot-208', 'status' => 'published']);
    $menu = assignedMenu();
    MenuItem::create(['menu_id' => $menu->id, 'order' => 0, 'type' => 'content', 'linkable_type' => $entry::class, 'linkable_id' => $entry->id, 'label' => 'Notre voiture vedette']);

    $tree = app(ResolveMenuTree::class)('primary');

    expect($tree[0]['label'])->toBe('Notre voiture vedette');
});

it('hides a content item whose entry is not published', function () {
    [, $modelClass] = buildMenuCarType();
    $entry = $modelClass::create(['brand' => 'Draft', 'slug' => 'draft', 'status' => 'draft']);
    $menu = assignedMenu();
    MenuItem::create(['menu_id' => $menu->id, 'order' => 0, 'type' => 'content', 'linkable_type' => $entry::class, 'linkable_id' => $entry->id]);

    expect(app(ResolveMenuTree::class)('primary'))->toBe([]);
});

it('hides a content item whose entry has been deleted', function () {
    [, $modelClass] = buildMenuCarType();
    $entry = $modelClass::create(['brand' => 'Gone', 'slug' => 'gone', 'status' => 'published']);
    $menu = assignedMenu();
    MenuItem::create(['menu_id' => $menu->id, 'order' => 0, 'type' => 'content', 'linkable_type' => $entry::class, 'linkable_id' => 999999]);

    expect(app(ResolveMenuTree::class)('primary'))->toBe([]);
});

it('resolves an archive item to the type archive URL', function () {
    [$contentType] = buildMenuCarType();
    $menu = assignedMenu();
    MenuItem::create(['menu_id' => $menu->id, 'order' => 0, 'type' => 'archive', 'content_type_key' => $contentType->key]);

    $tree = app(ResolveMenuTree::class)('primary');

    expect($tree[0]['url'])->toBe('/menu-cars');
});

it('resolves a custom link item as-is', function () {
    $menu = assignedMenu();
    MenuItem::create(['menu_id' => $menu->id, 'order' => 0, 'type' => 'custom_link', 'url' => 'https://example.com', 'label' => 'Example']);

    $tree = app(ResolveMenuTree::class)('primary');

    expect($tree[0]['url'])->toBe('https://example.com')
        ->and($tree[0]['label'])->toBe('Example');
});

it('resolves a section item with no URL, never active', function () {
    setCurrentPath('/');
    $menu = assignedMenu();
    MenuItem::create(['menu_id' => $menu->id, 'order' => 0, 'type' => 'section', 'label' => 'Section']);

    $tree = app(ResolveMenuTree::class)('primary');

    expect($tree[0]['url'])->toBeNull()
        ->and($tree[0]['is-active'])->toBeFalse();
});

it('nests children under their parent, in order', function () {
    $menu = assignedMenu();
    $parent = MenuItem::create(['menu_id' => $menu->id, 'order' => 0, 'type' => 'custom_link', 'url' => '/a', 'label' => 'A']);
    MenuItem::create(['menu_id' => $menu->id, 'parent_id' => $parent->id, 'order' => 1, 'type' => 'custom_link', 'url' => '/a2', 'label' => 'A2']);
    MenuItem::create(['menu_id' => $menu->id, 'parent_id' => $parent->id, 'order' => 0, 'type' => 'custom_link', 'url' => '/a1', 'label' => 'A1']);

    $tree = app(ResolveMenuTree::class)('primary');

    expect($tree[0]['children'])->toHaveCount(2)
        ->and($tree[0]['children'][0]['label'])->toBe('A1')
        ->and($tree[0]['children'][1]['label'])->toBe('A2')
        ->and($tree[0]['has-children'])->toBeTrue();
});

it('marks the item matching the current path as active, and its ancestors as ancestors', function () {
    $menu = assignedMenu();
    $parent = MenuItem::create(['menu_id' => $menu->id, 'order' => 0, 'type' => 'custom_link', 'url' => '/parent', 'label' => 'Parent']);
    MenuItem::create(['menu_id' => $menu->id, 'parent_id' => $parent->id, 'order' => 0, 'type' => 'custom_link', 'url' => '/parent/child', 'label' => 'Child']);
    setCurrentPath('/parent/child');

    $tree = app(ResolveMenuTree::class)('primary');

    expect($tree[0]['is-active'])->toBeFalse()
        ->and($tree[0]['is-ancestor'])->toBeTrue()
        ->and($tree[0]['children'][0]['is-active'])->toBeTrue();
});

it('hides an item restricted to guests when the actor is authenticated', function () {
    $menu = assignedMenu();
    MenuItem::create(['menu_id' => $menu->id, 'order' => 0, 'type' => 'custom_link', 'url' => '/a', 'label' => 'A', 'visibility' => 'guests']);

    $actor = User::create(['name' => 'Actor', 'email' => 'actor@example.com', 'password' => 'secret']);
    $this->actingAs($actor, 'baobab');

    expect(app(ResolveMenuTree::class)('primary'))->toBe([]);
});

it('shows an item restricted to authenticated users only when logged in', function () {
    $menu = assignedMenu();
    MenuItem::create(['menu_id' => $menu->id, 'order' => 0, 'type' => 'custom_link', 'url' => '/a', 'label' => 'A', 'visibility' => 'authenticated']);

    expect(app(ResolveMenuTree::class)('primary'))->toBe([]);

    $actor = User::create(['name' => 'Actor', 'email' => 'actor2@example.com', 'password' => 'secret']);
    $this->actingAs($actor, 'baobab');

    expect(app(ResolveMenuTree::class)('primary'))->toHaveCount(1);
});

it('lets a module inject items via the baobab.menu.items filter', function () {
    $menu = assignedMenu();
    MenuItem::create(['menu_id' => $menu->id, 'order' => 0, 'type' => 'custom_link', 'url' => '/a', 'label' => 'A']);

    Hook::modify('baobab.menu.items', function (array $items, string $locationKey) {
        $items[] = ['id' => 999, 'label' => 'Injected', 'url' => '/injected', 'type' => 'custom_link', 'target' => '_self', 'css_class' => null, 'icon' => null, 'visibility' => 'everyone', 'meta' => null, 'children' => []];

        return $items;
    }, priority: 5);

    $tree = app(ResolveMenuTree::class)('primary');

    expect(collect($tree)->pluck('label'))->toContain('Injected');
});

it('caches the resolved tree and reflects content changes only after invalidation', function () {
    [$contentType, $modelClass] = buildMenuCarType();
    $entry = $modelClass::create(['brand' => 'Original', 'slug' => 'car', 'status' => 'published']);
    $menu = assignedMenu();
    MenuItem::create(['menu_id' => $menu->id, 'order' => 0, 'type' => 'content', 'linkable_type' => $entry::class, 'linkable_id' => $entry->id]);

    expect(app(ResolveMenuTree::class)('primary')[0]['label'])->toBe('Original');

    $entry->update(['status' => 'draft']);
    Hook::action('baobab.content.saved', $contentType, $entry, false);

    expect(app(ResolveMenuTree::class)('primary'))->toBe([]);
});
