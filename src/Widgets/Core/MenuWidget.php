<?php

declare(strict_types=1);

namespace Baobab\Widgets\Core;

use Baobab\Menus\Actions\ResolveMenuTree;
use Baobab\Themes\Models\ThemeMenuLocation;
use Baobab\Widgets\Models\WidgetInstance;
use Baobab\Widgets\Widget;

/**
 * « Menu » (spec 10 §3.2) — rend un menu construit au point 4a. Compose les
 * deux systèmes plutôt que de dupliquer la résolution d'arbre : `data()`
 * délègue à `ResolveMenuTree`, la vue réutilise le partial
 * `baobab::menus.tree` existant.
 */
final class MenuWidget extends Widget
{
    public function __construct(private readonly ResolveMenuTree $resolveMenuTree) {}

    public static function key(): string
    {
        return 'baobab.menu';
    }

    public static function label(): string
    {
        return __('baobab::admin.widgets.menu_label');
    }

    public function settingsSchema(): array
    {
        $choiceOptions = ThemeMenuLocation::where('is_active', true)
            ->get()
            ->mapWithKeys(fn (ThemeMenuLocation $location): array => [$location->key => $location->label])
            ->all();

        return [
            [
                'key' => 'location',
                'type' => 'select',
                'label' => __('baobab::admin.widgets.menu_location_label'),
                'required' => true,
                'options' => ['choices' => array_keys($choiceOptions)],
                'choice_options' => $choiceOptions,
            ],
        ];
    }

    public function data(WidgetInstance $instance): array
    {
        $location = $instance->settings['location'] ?? null;

        return ['items' => $location !== null ? ($this->resolveMenuTree)($location) : []];
    }

    public function view(): string
    {
        return 'baobab::widgets.menu';
    }

    public function cacheTtl(): ?int
    {
        return null;
    }
}
