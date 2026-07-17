<?php

declare(strict_types=1);

namespace Baobab\Admin\Menus\Http\Controllers;

use Baobab\ContentTypes\Models\ContentType;
use Baobab\Menus\Actions\AssignMenuToLocations;
use Baobab\Menus\Actions\CreateMenu;
use Baobab\Menus\Actions\DeleteMenu;
use Baobab\Menus\Actions\SaveMenuItems;
use Baobab\Menus\Exceptions\MenuDepthExceededException;
use Baobab\Menus\Models\Menu;
use Baobab\Menus\Models\MenuItem;
use Baobab\Themes\Models\ThemeMenuLocation;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Écran « Menus » (spec 10 §2.3) — construction, assignation aux
 * emplacements du thème actif. Accès gouverné par `baobab.menus.manage`
 * (routes/admin.php).
 */
final class MenusController
{
    public function index(): View
    {
        return view('baobab::admin.menus.index', [
            'menus' => Menu::withCount('items')->with('assignments')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('baobab::admin.menus.create');
    }

    public function store(Request $request, CreateMenu $action): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $menu = $action($validated['name'], $validated['description'] ?? null);

        return redirect()->route('admin.menus.edit', ['menu' => $menu->id]);
    }

    public function edit(Menu $menu): View
    {
        $menu->load(['items' => fn ($query) => $query->orderBy('order')]);

        $assignedKeys = $menu->assignments()->pluck('location_key');

        $locations = ThemeMenuLocation::where('is_active', true)
            ->orWhereIn('key', $assignedKeys)
            ->orderBy('label')
            ->get();

        return view('baobab::admin.menus.edit', [
            'menu' => $menu,
            'itemsTree' => $this->tree($menu->items, null),
            'locations' => $locations,
            'assignedKeys' => $assignedKeys,
            'contentTypes' => ContentType::where('is_addressable', true)->get(),
        ]);
    }

    public function update(Menu $menu, Request $request, SaveMenuItems $saveItems, AssignMenuToLocations $assignLocations): RedirectResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'string'],
            'locations' => ['array'],
            'locations.*' => ['string'],
        ]);

        /** @var list<array<string, mixed>>|null $items */
        $items = json_decode($validated['items'], associative: true);

        try {
            $saveItems($menu, $items ?? []);
        } catch (MenuDepthExceededException $e) {
            return back()->withErrors(['items' => $e->getMessage()]);
        }

        $assignLocations($menu, $validated['locations'] ?? []);

        session()->flash('toast', ['type' => 'success', 'message' => __('baobab::admin.menus.updated')]);

        return redirect()->route('admin.menus.edit', ['menu' => $menu->id]);
    }

    public function destroy(Menu $menu, DeleteMenu $action): RedirectResponse
    {
        $action($menu);

        session()->flash('toast', ['type' => 'success', 'message' => __('baobab::admin.menus.deleted')]);

        return redirect()->route('admin.menus.index');
    }

    public function searchContent(Request $request): JsonResponse
    {
        $query = (string) $request->query('q', '');

        if (mb_strlen($query) < 2) {
            return response()->json([]);
        }

        $results = [];

        foreach (ContentType::where('is_addressable', true)->get() as $contentType) {
            /** @var string|null $titleField */
            $titleField = $contentType->blueprint['title_field'] ?? null;

            if ($titleField === null) {
                continue;
            }

            /** @var class-string<Model> $modelClass */
            $modelClass = $contentType->modelClass();

            foreach ($modelClass::query()->where($titleField, 'like', "%{$query}%")->limit(5)->get() as $entry) {
                $results[] = [
                    'linkable_type' => $entry::class,
                    'linkable_id' => $entry->getKey(),
                    'label' => $entry->getAttribute($titleField),
                    'content_type' => $contentType->key,
                ];
            }
        }

        return response()->json($results);
    }

    /**
     * @param  Collection<int, MenuItem>  $items
     * @return array<int, array<string, mixed>>
     */
    private function tree(Collection $items, ?int $parentId): array
    {
        return $items
            ->where('parent_id', $parentId)
            ->map(fn (MenuItem $item): array => [
                'id' => $item->id,
                'type' => $item->type,
                'linkable_type' => $item->linkable_type,
                'linkable_id' => $item->linkable_id,
                'content_type_key' => $item->content_type_key,
                'url' => $item->url,
                'label' => $item->label,
                'target' => $item->target,
                'css_class' => $item->css_class,
                'icon' => $item->icon,
                'visibility' => $item->visibility,
                'meta' => $item->meta,
                'children' => $this->tree($items, $item->id),
            ])
            ->values()
            ->all();
    }
}
