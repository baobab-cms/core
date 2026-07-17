<?php

use Baobab\Menus\Actions\ResolveMenuTree;
use Baobab\Menus\Actions\SaveMenuItems;
use Baobab\Menus\Exceptions\MenuDepthExceededException;
use Baobab\Menus\Models\Menu;
use Baobab\Menus\Models\MenuAssignment;
use Baobab\Menus\Models\MenuItem;
use Baobab\Themes\Models\ThemeMenuLocation;

function flatItem(int $depth, string $label, array $overrides = []): array
{
    return array_replace([
        'depth' => $depth,
        'type' => 'custom_link',
        'url' => "/{$label}",
        'label' => $label,
        'target' => '_self',
        'visibility' => 'everyone',
    ], $overrides);
}

it('rebuilds the parent_id hierarchy from a flat depth-annotated list', function () {
    $menu = Menu::create(['name' => 'Main']);

    app(SaveMenuItems::class)($menu, [
        flatItem(0, 'a'),
        flatItem(1, 'a1'),
        flatItem(1, 'a2'),
        flatItem(0, 'b'),
    ]);

    $a = MenuItem::where('label', 'a')->firstOrFail();
    $a1 = MenuItem::where('label', 'a1')->firstOrFail();
    $a2 = MenuItem::where('label', 'a2')->firstOrFail();
    $b = MenuItem::where('label', 'b')->firstOrFail();

    expect($a->parent_id)->toBeNull()
        ->and($b->parent_id)->toBeNull()
        ->and($a1->parent_id)->toBe($a->id)
        ->and($a2->parent_id)->toBe($a->id)
        ->and($a1->order)->toBe(0)
        ->and($a2->order)->toBe(1)
        ->and($b->order)->toBe(1);
});

it('rejects a submission deeper than the configured maximum', function () {
    config(['baobab.menus.max_depth' => 2]);
    $menu = Menu::create(['name' => 'Main']);

    app(SaveMenuItems::class)($menu, [
        flatItem(0, 'a'),
        flatItem(1, 'a1'),
        flatItem(2, 'a1a'),
    ]);
})->throws(MenuDepthExceededException::class);

it('replaces existing items entirely rather than appending', function () {
    $menu = Menu::create(['name' => 'Main']);
    app(SaveMenuItems::class)($menu, [flatItem(0, 'old')]);

    app(SaveMenuItems::class)($menu, [flatItem(0, 'new')]);

    expect(MenuItem::where('menu_id', $menu->id)->count())->toBe(1)
        ->and(MenuItem::where('menu_id', $menu->id)->firstOrFail()->label)->toBe('new');
});

it('invalidates the resolved-tree cache after saving', function () {
    ThemeMenuLocation::create(['key' => 'primary', 'label' => 'Primary', 'is_active' => true]);
    $menu = Menu::create(['name' => 'Main']);
    MenuAssignment::create(['menu_id' => $menu->id, 'location_key' => 'primary']);
    app(SaveMenuItems::class)($menu, [flatItem(0, 'first')]);
    app(ResolveMenuTree::class)('primary'); // warms the cache

    app(SaveMenuItems::class)($menu, [flatItem(0, 'second')]);

    expect(app(ResolveMenuTree::class)('primary')[0]['label'])->toBe('second');
});
