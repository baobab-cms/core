<?php

declare(strict_types=1);

namespace Baobab\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * `<x-baobab::button variant="primary">` — bouton, ou lien qui en a l'allure
 * dès qu'un `href` est passé.
 *
 * La table des variantes et l'assemblage de la chaîne de classes vivaient dans
 * un bloc `@php` de la vue (suivi n° 138).
 *
 * `text-white` sur les aplats pleins est **conservé délibérément** : contrôlé
 * le 14 août 2026, la spec 18 §13.4 ne le proscrit pas — sa règle 2 vise le
 * noir pur et `sand` sur fond coloré, jamais le blanc, et sa règle 1 autorise
 * explicitement un bouton `baobab-500` plein par écran, lequel appelle un
 * premier plan clair. Le vocabulaire de tokens ne comporte par ailleurs aucune
 * couleur exprimant « premier plan lisible sur primary » : le sujet est un
 * manque de vocabulaire, consigné au suivi, pas un écart à corriger ici.
 */
final class Button extends Component
{
    /** @var array<string, string> */
    private const VARIANTS = [
        'primary' => 'bg-primary text-white hover:opacity-90',
        'secondary' => 'border border-border bg-surface text-foreground hover:bg-surface-subtle',
        'danger' => 'bg-danger text-white hover:opacity-90',
        'ghost' => 'text-foreground hover:bg-surface-subtle',
    ];

    private const BASE = 'inline-flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium disabled:cursor-not-allowed disabled:opacity-50';

    public string $classes;

    public function __construct(
        public string $variant = 'primary',
        public ?string $href = null,
        public string $type = 'button',
    ) {
        $this->classes = self::BASE.' '.(self::VARIANTS[$this->variant] ?? self::VARIANTS['primary']);
    }

    public function render(): View
    {
        return view('baobab::components.button');
    }
}
