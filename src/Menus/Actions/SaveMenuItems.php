<?php

declare(strict_types=1);

namespace Baobab\Menus\Actions;

use Baobab\Menus\Exceptions\MenuDepthExceededException;
use Baobab\Menus\Models\Menu;
use Baobab\Menus\Models\MenuItem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Remplace l'intégralité des items d'un menu à partir de la liste **à plat**
 * soumise par le constructeur (spec 10 §2.3) — un seul POST, patron du champ
 * `gallery` (M4 point 4b-ii) : pas de diff granulaire, delete + recreate
 * dans une transaction. La hiérarchie voyage comme un entier `depth`
 * (0 = racine) plutôt qu'un arbre `children` imbriqué : plus simple à
 * produire côté Alpine (une seule liste affichée avec indentation visuelle,
 * patron de l'éditeur de menus WordPress) qu'à reconstruire un arbre en JS
 * avant soumission — la reconstruction en `parent_id` se fait ici, côté
 * serveur, à partir du dernier item vu à chaque profondeur.
 */
final class SaveMenuItems
{
    /**
     * @param  list<array<string, mixed>>  $items  À plat, dans l'ordre final
     *                                             d'affichage — chaque entrée
     *                                             porte `depth` (0 = racine).
     *
     * @throws MenuDepthExceededException
     */
    public function __invoke(Menu $menu, array $items): void
    {
        $max = (int) config('baobab.menus.max_depth', 4);

        foreach ($items as $item) {
            if ((int) ($item['depth'] ?? 0) + 1 > $max) {
                throw MenuDepthExceededException::forMax($max);
            }
        }

        DB::transaction(function () use ($menu, $items): void {
            $menu->items()->delete();

            /** @var array<int, int> $lastIdAtDepth */
            $lastIdAtDepth = [];
            /** @var array<int, int> $orderAtDepth */
            $orderAtDepth = [];

            foreach ($items as $item) {
                $depth = (int) ($item['depth'] ?? 0);
                $parentId = $depth > 0 ? ($lastIdAtDepth[$depth - 1] ?? null) : null;
                $order = $orderAtDepth[$depth] ?? 0;

                $created = MenuItem::create([
                    'menu_id' => $menu->id,
                    'parent_id' => $parentId,
                    'order' => $order,
                    'type' => $item['type'],
                    'linkable_type' => $item['linkable_type'] ?? null,
                    'linkable_id' => $item['linkable_id'] ?? null,
                    'content_type_key' => $item['content_type_key'] ?? null,
                    'url' => $item['url'] ?? null,
                    'label' => $item['label'] ?? null,
                    'target' => $item['target'] ?? '_self',
                    'css_class' => $item['css_class'] ?? null,
                    'icon' => $item['icon'] ?? null,
                    'visibility' => $item['visibility'] ?? 'everyone',
                    'meta' => $item['meta'] ?? null,
                ]);

                $lastIdAtDepth[$depth] = $created->id;
                $orderAtDepth[$depth] = $order + 1;

                foreach (array_keys($lastIdAtDepth) as $deeper) {
                    if ($deeper > $depth) {
                        unset($lastIdAtDepth[$deeper], $orderAtDepth[$deeper]);
                    }
                }
            }
        });

        Cache::forget("baobab.menu.{$menu->id}");
    }
}
