<?php

declare(strict_types=1);

namespace Baobab\Menus\Actions;

use Baobab\Menus\Models\Menu;

final class CreateMenu
{
    public function __invoke(string $name, ?string $description = null): Menu
    {
        return Menu::create(['name' => $name, 'description' => $description]);
    }
}
