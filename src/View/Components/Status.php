<?php

declare(strict_types=1);

namespace Baobab\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * `<x-baobab::status variant="secondary">Publié</x-baobab::status>` — pastille
 * de statut de contenu (`direction-visuelle.md` §8.5) : point coloré +
 * libellé, jamais le point seul.
 *
 * Vocabulaire distinct de `<x-baobab::badge>` (statuts génériques, une
 * vingtaine d'écrans déjà dessus, vocabulaire neutral/success/warning/danger/
 * info sans rapport avec la publication) — composant séparé plutôt qu'un
 * second vocabulaire porté par Badge (suivi n° 376 décision 1).
 */
final class Status extends Component
{
    /** @var array<string, string> */
    private const VARIANTS = [
        'secondary' => 'bg-leaf-50 text-leaf-600',
        'muted' => 'bg-sand-100 text-sand-600',
        'warning' => 'bg-warning-50 text-warning-700',
        'danger' => 'bg-danger-50 text-danger-700',
    ];

    /** @var array<string, string> */
    private const DOTS = [
        'secondary' => 'bg-leaf-600',
        'muted' => 'bg-sand-600',
        'warning' => 'bg-warning-700',
        'danger' => 'bg-danger-700',
    ];

    public string $classes;

    public string $dotClass;

    public function __construct(public string $variant = 'muted')
    {
        $this->classes = self::VARIANTS[$this->variant] ?? self::VARIANTS['muted'];
        $this->dotClass = self::DOTS[$this->variant] ?? self::DOTS['muted'];
    }

    public function render(): View
    {
        return view('baobab::components.status');
    }
}
