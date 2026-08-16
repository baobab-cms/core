<?php

use Baobab\Access\Actions\GrantPermission;
use Baobab\ContentTypes\Actions\BuildContentType;
use Baobab\ContentTypes\Models\ContentType;
use Baobab\Menus\Models\Menu;
use Baobab\Menus\Models\MenuAssignment;
use Baobab\Menus\Models\MenuItem;
use Baobab\Modules\Models\Module;
use Baobab\Modules\ModuleAutoloader;
use Baobab\Themes\Models\ThemeMenuLocation;
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
function buildMenuSearchCarType(): array
{
    $contentType = app(BuildContentType::class)(contentTypeBlueprintJson('MenuSearchPage', [
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

/**
 * @param  list<string>  $permissions
 */
function menusActor(array $permissions): User
{
    static $counter = 0;
    $counter++;

    $user = User::create([
        'name' => "Menus Actor {$counter}",
        'email' => "menus-actor-{$counter}@example.com",
        'password' => 'secret',
    ]);

    app(GrantPermission::class)($user, 'baobab.admin.access');

    foreach ($permissions as $permission) {
        app(GrantPermission::class)($user, $permission);
    }

    return $user;
}

it('denies the menus screen without baobab.menus.manage', function () {
    $actor = menusActor([]);

    $this->actingAs($actor, 'baobab')
        ->get(route('admin.menus.index'))
        ->assertForbidden();
});

it('renders the builder screen for an existing menu, including nested items and locations', function () {
    ThemeMenuLocation::create(['key' => 'primary', 'label' => 'Primary', 'is_active' => true]);
    $menu = Menu::create(['name' => 'Main']);
    $parent = MenuItem::create(['menu_id' => $menu->id, 'order' => 0, 'type' => 'custom_link', 'url' => '/a', 'label' => 'A']);
    MenuItem::create(['menu_id' => $menu->id, 'parent_id' => $parent->id, 'order' => 0, 'type' => 'section', 'label' => 'A child']);
    MenuAssignment::create(['menu_id' => $menu->id, 'location_key' => 'primary']);
    $actor = menusActor(['baobab.menus.manage']);

    $this->actingAs($actor, 'baobab')
        ->get(route('admin.menus.edit', ['menu' => $menu->id]))
        ->assertOk()
        ->assertSee('Main')
        ->assertSee('Primary');
});

it('creates a menu over HTTP', function () {
    $actor = menusActor(['baobab.menus.manage']);

    $this->actingAs($actor, 'baobab')
        ->post(route('admin.menus.store'), ['name' => 'Main menu'])
        ->assertRedirect();

    expect(Menu::where('name', 'Main menu')->exists())->toBeTrue();
});

it('saves the item tree and location assignments in one request', function () {
    ThemeMenuLocation::create(['key' => 'primary', 'label' => 'Primary', 'is_active' => true]);
    $menu = Menu::create(['name' => 'Main']);
    $actor = menusActor(['baobab.menus.manage']);

    $items = json_encode([
        ['depth' => 0, 'type' => 'custom_link', 'url' => '/about', 'label' => 'About', 'target' => '_self', 'visibility' => 'everyone'],
    ]);

    $this->actingAs($actor, 'baobab')
        ->post(route('admin.menus.update', ['menu' => $menu->id]), [
            'items' => $items,
            'locations' => ['primary'],
        ])
        ->assertRedirect(route('admin.menus.edit', ['menu' => $menu->id]))
        ->assertSessionHas('toast');

    expect(MenuItem::where('menu_id', $menu->id)->where('label', 'About')->exists())->toBeTrue()
        ->and(MenuAssignment::where('menu_id', $menu->id)->where('location_key', 'primary')->exists())->toBeTrue();
});

it('deletes a menu, cascading its items and assignments', function () {
    ThemeMenuLocation::create(['key' => 'primary', 'label' => 'Primary', 'is_active' => true]);
    $menu = Menu::create(['name' => 'Main']);
    MenuItem::create(['menu_id' => $menu->id, 'order' => 0, 'type' => 'custom_link', 'url' => '/x', 'label' => 'X']);
    MenuAssignment::create(['menu_id' => $menu->id, 'location_key' => 'primary']);
    $actor = menusActor(['baobab.menus.manage']);

    $this->actingAs($actor, 'baobab')
        ->delete(route('admin.menus.destroy', ['menu' => $menu->id]))
        ->assertRedirect(route('admin.menus.index'));

    expect(Menu::find($menu->id))->toBeNull()
        ->and(MenuItem::where('menu_id', $menu->id)->exists())->toBeFalse()
        ->and(MenuAssignment::where('menu_id', $menu->id)->exists())->toBeFalse();
});

it('searches addressable content by title across content types', function () {
    [, $modelClass] = buildMenuSearchCarType();
    $modelClass::create(['brand' => 'Peugeot 208', 'slug' => 'peugeot-208', 'status' => 'published']);
    $actor = menusActor(['baobab.menus.manage']);

    $response = $this->actingAs($actor, 'baobab')
        ->get(route('admin.menus.search-content', ['q' => 'Peugeot']));

    $response->assertOk()->assertJsonFragment(['label' => 'Peugeot 208']);
});
