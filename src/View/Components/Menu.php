<?php

declare(strict_types=1);

namespace Baobab\View\Components;

use Baobab\Menus\Actions\ResolveMenuTree;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\View\Component;

/**
 * `<x-baobab::menu location="primary" />` (spec 10 §2.4) — composant à
 * classe plutôt qu'anonyme : résout l'arbre (appel d'Action), aucune
 * logique dans la vue Blade elle-même (convention du projet, au-delà des
 * seules vues de thèmes).
 */
final class Menu extends Component
{
    /**
     * @var list<array<string, mixed>>
     */
    public array $items;

    public function __construct(ResolveMenuTree $resolveMenuTree, public string $location, public ?int $depth = null)
    {
        $this->items = $resolveMenuTree($location);
    }

    public function render(): ViewContract
    {
        return view('baobab::components.menu');
    }
}
