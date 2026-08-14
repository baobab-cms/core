<?php

declare(strict_types=1);

namespace Baobab\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * `<x-baobab::page :title="…" :breadcrumbs="…">` — en-tête d'écran admin :
 * fil d'Ariane, titre de page et emplacement d'actions.
 *
 * Le dépliage de chaque miette vivait dans un bloc `@php` **à l'intérieur de la
 * boucle** (suivi n° 138) : chaque tour décidait si la miette était une chaîne
 * ou un couple `[libellé, url]`. La normalisation se fait désormais une fois,
 * à la construction, et la vue ne parcourt plus qu'une liste homogène.
 */
final class Page extends Component
{
    /** @var list<array{label: string, url: string|null}> */
    public array $crumbs = [];

    /**
     * @param  array<int, mixed>  $breadcrumbs  chaîne, ou couple `[libellé, url]`
     */
    public function __construct(
        public ?string $title = null,
        array $breadcrumbs = [],
    ) {
        foreach ($breadcrumbs as $crumb) {
            if (is_array($crumb)) {
                $label = $crumb[0] ?? '';
                $url = $crumb[1] ?? null;
            } else {
                $label = $crumb;
                $url = null;
            }

            $this->crumbs[] = [
                'label' => is_string($label) ? $label : '',
                'url' => is_string($url) && $url !== '' ? $url : null,
            ];
        }
    }

    public function render(): View
    {
        return view('baobab::components.page');
    }
}
