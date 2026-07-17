<?php

declare(strict_types=1);

namespace Baobab\Menus\Actions;

use Baobab\ContentTypes\Models\ContentType;
use Baobab\Facades\Hook;
use Baobab\Menus\Models\MenuAssignment;
use Baobab\Menus\Models\MenuItem;
use Illuminate\Support\Facades\Cache;

/**
 * Résout l'arbre d'un emplacement de menu (spec 10 §2.4). Le cache ne porte
 * que la structure coûteuse à calculer (résolution de contenu, URLs) — la
 * visibilité et l'état actif/ancêtre sont appliqués à chaque requête sur le
 * résultat mis en cache (pas de requête, bon marché). Un item `content` dont
 * l'entrée est introuvable ou non publiée est omis de l'arbre mis en cache
 * (spec §2.2 : « masqué automatiquement »).
 */
final class ResolveMenuTree
{
    /**
     * @return list<array<string, mixed>>
     */
    public function __invoke(string $locationKey): array
    {
        $assignment = MenuAssignment::where('location_key', $locationKey)->first();

        if (! $assignment instanceof MenuAssignment) {
            return [];
        }

        /** @var list<array<string, mixed>> $tree */
        $tree = Cache::remember(
            "baobab.menu.{$assignment->menu_id}",
            (int) config('baobab.menus.cache_ttl', 3600),
            fn (): array => $this->buildTree($assignment->menu_id, null),
        );

        /** @var list<array<string, mixed>>|null $filtered */
        $filtered = Hook::filter('baobab.menu.items', $tree, $locationKey);

        return $this->applyLiveState($filtered ?? [], '/'.ltrim(request()->path(), '/'));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildTree(int $menuId, ?int $parentId): array
    {
        $items = MenuItem::where('menu_id', $menuId)
            ->where('parent_id', $parentId)
            ->orderBy('order')
            ->get();

        $nodes = [];

        foreach ($items as $item) {
            $resolved = $this->resolve($item);

            if ($resolved === null) {
                continue;
            }

            $resolved['children'] = $this->buildTree($menuId, $item->id);
            $nodes[] = $resolved;
        }

        return $nodes;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolve(MenuItem $item): ?array
    {
        return match ($item->type) {
            'content' => $this->resolveContent($item),
            'archive' => $this->resolveArchive($item),
            'custom_link' => $this->resolveCustomLink($item),
            'section' => $this->resolveSection($item),
            default => null,
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveContent(MenuItem $item): ?array
    {
        $entry = $item->linkable;

        if ($entry === null || $entry->getAttribute('status') !== 'published') {
            return null;
        }

        $contentType = ContentType::forModelClass($entry::class);

        if ($contentType === null) {
            return null;
        }

        /** @var string|null $titleField */
        $titleField = $contentType->blueprint['title_field'] ?? null;
        $defaultLabel = $titleField !== null ? (string) $entry->getAttribute($titleField) : $contentType->key;

        return $this->node($item, $item->label ?? $defaultLabel, "/{$contentType->urlPrefix()}/{$entry->getAttribute('slug')}");
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveArchive(MenuItem $item): ?array
    {
        if ($item->content_type_key === null) {
            return null;
        }

        $contentType = ContentType::where('key', $item->content_type_key)->first();

        if ($contentType === null) {
            return null;
        }

        /** @var string $defaultLabel */
        $defaultLabel = $contentType->blueprint['label']['plural'] ?? $contentType->key;

        return $this->node($item, $item->label ?? $defaultLabel, "/{$contentType->urlPrefix()}");
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveCustomLink(MenuItem $item): ?array
    {
        if ($item->url === null || $item->label === null) {
            return null;
        }

        return $this->node($item, $item->label, $item->url);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveSection(MenuItem $item): ?array
    {
        if ($item->label === null) {
            return null;
        }

        return $this->node($item, $item->label, null);
    }

    /**
     * @return array<string, mixed>
     */
    private function node(MenuItem $item, string $label, ?string $url): array
    {
        return [
            'id' => $item->id,
            'label' => $label,
            'url' => $url,
            'type' => $item->type,
            'target' => $item->target,
            'css_class' => $item->css_class,
            'icon' => $item->icon,
            'visibility' => $item->visibility,
            'meta' => $item->meta,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     * @return list<array<string, mixed>>
     */
    private function applyLiveState(array $nodes, string $currentPath): array
    {
        $result = [];

        foreach ($nodes as $node) {
            if (! $this->isVisible((string) $node['visibility'])) {
                continue;
            }

            /** @var list<array<string, mixed>> $children */
            $children = $node['children'] ?? [];
            $children = $this->applyLiveState($children, $currentPath);

            $isActive = $node['url'] !== null && rtrim((string) $node['url'], '/') === rtrim($currentPath, '/');
            $isAncestor = collect($children)->contains(fn (array $c): bool => $c['is-active'] || $c['is-ancestor']);

            $node['children'] = $children;
            $node['is-active'] = $isActive;
            $node['is-ancestor'] = $isAncestor;
            $node['has-children'] = $children !== [];

            $result[] = $node;
        }

        return $result;
    }

    private function isVisible(string $visibility): bool
    {
        return match (true) {
            $visibility === 'everyone' => true,
            $visibility === 'guests' => ! auth('baobab')->check(),
            $visibility === 'authenticated' => auth('baobab')->check(),
            default => (bool) auth('baobab')->user()?->can($visibility),
        };
    }
}
