<?php

declare(strict_types=1);

namespace Baobab\Menus\Actions;

use Baobab\Menus\Models\Menu;
use Baobab\Menus\Models\MenuAssignment;
use Illuminate\Support\Facades\DB;

/**
 * Remplace les emplacements assignés à un menu (spec 10 §2.3 : « multi-
 * assignation possible »). Pas d'invalidation de cache nécessaire ici :
 * `ResolveMenuTree` résout l'assignation emplacement → menu à chaque
 * requête (jamais mis en cache), seul l'arbre d'un menu déjà résolu l'est —
 * réassigner un emplacement à un autre menu prend effet immédiatement.
 */
final class AssignMenuToLocations
{
    /**
     * @param  list<string>  $locationKeys
     */
    public function __invoke(Menu $menu, array $locationKeys): void
    {
        DB::transaction(function () use ($menu, $locationKeys): void {
            $menu->assignments()->delete();

            foreach ($locationKeys as $key) {
                MenuAssignment::create(['menu_id' => $menu->id, 'location_key' => $key]);
            }
        });
    }
}
