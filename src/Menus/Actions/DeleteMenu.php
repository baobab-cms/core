<?php

declare(strict_types=1);

namespace Baobab\Menus\Actions;

use Baobab\Menus\Models\Menu;
use Illuminate\Support\Facades\Cache;

/**
 * Supprime un menu — items et assignations en cascade (contraintes FK,
 * migrations `create_menu_items_table`/`create_menu_assignments_table`).
 */
final class DeleteMenu
{
    public function __invoke(Menu $menu): void
    {
        Cache::forget("baobab.menu.{$menu->id}");
        $menu->delete();
    }
}
