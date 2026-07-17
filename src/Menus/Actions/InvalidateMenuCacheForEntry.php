<?php

declare(strict_types=1);

namespace Baobab\Menus\Actions;

use Baobab\Menus\Models\MenuItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Invalide le cache de tout menu référençant l'entrée donnée (spec 10 §2.4 :
 * « invalidé... aux transitions des contenus référencés ») — appelée par les
 * listeners `baobab.content.saved`/`baobab.content.transitioned`
 * (BaobabServiceProvider), patron `SyncMediaUsagesFromEntry`.
 */
final class InvalidateMenuCacheForEntry
{
    public function __invoke(Model $entry): void
    {
        $menuIds = MenuItem::where('linkable_type', $entry::class)
            ->where('linkable_id', $entry->getKey())
            ->pluck('menu_id')
            ->unique();

        foreach ($menuIds as $menuId) {
            Cache::forget("baobab.menu.{$menuId}");
        }
    }
}
