<?php

declare(strict_types=1);

namespace Baobab\View\Components;

use Baobab\Widgets\Actions\ResolveWidgetZone;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\View\Component;

/**
 * `<x-baobab::widget-zone name="sidebar" />` (spec 10 §3.4) — composant à
 * classe, patron exact `Menu.php` : résout la zone (Action), aucune
 * logique dans la vue Blade.
 */
final class WidgetZone extends Component
{
    /**
     * @var list<array<string, mixed>>
     */
    public array $items;

    public function __construct(ResolveWidgetZone $resolveWidgetZone, public string $name)
    {
        $this->items = $resolveWidgetZone($name);
    }

    public function render(): ViewContract
    {
        return view('baobab::components.widget-zone');
    }
}
