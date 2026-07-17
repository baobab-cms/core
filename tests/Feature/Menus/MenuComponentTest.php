<?php

use Baobab\Menus\Models\Menu;
use Baobab\Menus\Models\MenuAssignment;
use Baobab\Menus\Models\MenuItem;
use Baobab\Themes\Models\ThemeMenuLocation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;

it('renders a nested menu as semantic ul/li with is-active/has-children classes', function () {
    ThemeMenuLocation::create(['key' => 'primary', 'label' => 'Primary', 'is_active' => true]);
    $menu = Menu::create(['name' => 'Main']);
    $parent = MenuItem::create(['menu_id' => $menu->id, 'order' => 0, 'type' => 'custom_link', 'url' => '/parent', 'label' => 'Parent']);
    MenuItem::create(['menu_id' => $menu->id, 'parent_id' => $parent->id, 'order' => 0, 'type' => 'custom_link', 'url' => '/parent/child', 'label' => 'Child']);
    MenuAssignment::create(['menu_id' => $menu->id, 'location_key' => 'primary']);

    app()->instance('request', Request::create('/parent/child'));

    $html = Blade::render('<x-baobab::menu location="primary" />');

    expect($html)->toContain('role="menu"')
        ->and($html)->toContain('Parent')
        ->and($html)->toContain('Child')
        ->and($html)->toContain('has-children')
        ->and($html)->toContain('is-ancestor');
});

it('renders nothing for a location with no assignment', function () {
    $html = Blade::render('<x-baobab::menu location="unassigned" />');

    expect(trim($html))->toBe('');
});
