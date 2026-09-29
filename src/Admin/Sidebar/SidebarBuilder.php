<?php

declare(strict_types=1);

namespace Baobab\Admin\Sidebar;

use Baobab\Facades\Hook;
use Baobab\Modules\Models\ModuleMenuItem;
use Baobab\Users\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

/**
 * Construit le menu admin exclusivement depuis les `menus.admin` des manifests
 * (persistés dans `module_menu_items` à l'installation), filtré par les
 * permissions de l'utilisateur et étendu via le filtre `baobab.admin.menu`
 * (spec 04 §3.3). Lecture pure recalculée à chaque requête — pas une Action
 * (pas de mutation, pas de surface CLI/API équivalente).
 */
final class SidebarBuilder
{
    /** @return Collection<int, SidebarItem> */
    public function build(?User $user): Collection
    {
        if ($user === null) {
            return new Collection;
        }

        /** @var EloquentCollection<int, ModuleMenuItem> $items */
        $items = ModuleMenuItem::query()
            ->whereNull('parent_id')
            ->whereHas('module', fn (Builder $query): Builder => $query->where('status', 'active'))
            ->with('children')
            ->orderBy('order')
            ->get();

        $filtered = $this->filterItems($items, $user);

        /** @var Collection<int, SidebarItem> $result */
        $result = Hook::filter('baobab.admin.menu', $filtered, $user);

        return collect($this->applyActiveState(array_values($result->all()), '/'.ltrim(request()->path(), '/')));
    }

    /**
     * Marque le lien dont l'URL correspond à la page courante (`aria-current`,
     * spec-admin §10 — même patron que `ResolveMenuTree::applyLiveState`).
     * Reconstruit l'arbre plutôt que de muter : `SidebarItem` est immuable.
     *
     * Un groupe (suivi n° 383) n'a pas d'URL propre : il est marqué actif
     * quand l'un de ses enfants l'est, pour rester repérable même replié —
     * les enfants sont donc résolus d'abord, le groupe hérite ensuite.
     *
     * @param  list<SidebarItem>  $items
     * @return list<SidebarItem>
     */
    private function applyActiveState(array $items, string $currentPath): array
    {
        return array_map(function (SidebarItem $item) use ($currentPath): SidebarItem {
            $children = $this->applyActiveState($item->children, $currentPath);

            $itemPath = $item->url !== null ? '/'.ltrim((string) parse_url($item->url, PHP_URL_PATH), '/') : null;
            $isActive = $itemPath !== null && rtrim($itemPath, '/') === rtrim($currentPath, '/');

            if (! $isActive) {
                $isActive = collect($children)->contains(fn (SidebarItem $child): bool => $child->isActive);
            }

            return new SidebarItem(
                id: $item->id,
                label: $item->label,
                icon: $item->icon,
                url: $item->url,
                order: $item->order,
                children: $children,
                isActive: $isActive,
            );
        }, $items);
    }

    /**
     * @param  EloquentCollection<int, ModuleMenuItem>  $items
     * @return Collection<int, SidebarItem>
     */
    private function filterItems(EloquentCollection $items, User $user): Collection
    {
        return $items
            ->map(fn (ModuleMenuItem $item): ?SidebarItem => $this->toSidebarItem($item, $user))
            ->filter()
            ->values();
    }

    private function toSidebarItem(ModuleMenuItem $item, User $user): ?SidebarItem
    {
        if ($item->permission !== null && ! $user->can($item->permission)) {
            return null;
        }

        $children = $this->filterItems($item->children, $user);

        if ($item->route === null && $children->isEmpty()) {
            return null;
        }

        return new SidebarItem(
            id: $item->id,
            label: $item->label,
            icon: $item->icon,
            url: $this->resolveUrl($item->route, $item->route_params ?? []),
            order: $item->order,
            children: array_values($children->all()),
        );
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function resolveUrl(?string $routeName, array $params = []): ?string
    {
        if ($routeName === null || ! Route::has($routeName)) {
            return null;
        }

        return route($routeName, $params);
    }
}
