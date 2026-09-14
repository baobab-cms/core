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

    expect($html)->toContain('<ul>')
        ->and($html)->toContain('Parent')
        ->and($html)->toContain('Child')
        ->and($html)->toContain('has-children')
        ->and($html)->toContain('is-ancestor')
        // suivi n° 307 constat 5 : ni role="menu"/"none"/"menuitem", qui
        // annoncent un widget applicatif qu'aucun script ne fournit ici —
        // une navigation de site est une simple liste de liens.
        ->and($html)->not->toContain('role="menu"')
        ->and($html)->not->toContain('role="none"')
        ->and($html)->not->toContain('role="menuitem"');
});

it('renders nothing for a location with no assignment', function () {
    $html = Blade::render('<x-baobab::menu location="unassigned" />');

    expect(trim($html))->toBe('');
});

/**
 * n° 182 — l'icône imprimait le nom brut (`bi-cup-straw`) au lieu de passer
 * par `<x-baobab::icon>` : amendement spec 10 §2.2 du 8 septembre 2026, le
 * Core rend l'icône par défaut, pas un point de personnalisation par thème.
 */
it('renders a menu item icon through the icon component rather than printing its name', function () {
    ThemeMenuLocation::create(['key' => 'primary', 'label' => 'Primary', 'is_active' => true]);
    $menu = Menu::create(['name' => 'Main']);
    MenuItem::create(['menu_id' => $menu->id, 'order' => 0, 'type' => 'custom_link', 'url' => '/cafe', 'label' => 'Café', 'icon' => 'bi-cup-straw']);
    MenuAssignment::create(['menu_id' => $menu->id, 'location_key' => 'primary']);

    app()->instance('request', Request::create('/'));

    $html = Blade::render('<x-baobab::menu location="primary" />');

    expect($html)->not->toContain('>bi-cup-straw<')
        ->and($html)->toContain('<svg');
});
