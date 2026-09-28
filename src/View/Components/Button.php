<?php

declare(strict_types=1);

namespace Baobab\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * `<x-baobab::button variant="primary" size="sm">` — bouton, ou lien qui en a
 * l'allure dès qu'un `href` est passé.
 *
 * La table des variantes et l'assemblage de la chaîne de classes vivaient dans
 * un bloc `@php` de la vue (suivi n° 138).
 *
 * Le remplissage `primary` consomme `bg-primary-strong` (`--bb-color-primary-strong`,
 * dérivé par `ContrastSafeColor` dans `CompileDesignTokens` — jamais `bg-primary`
 * brut, sous le seuil AA à 4,49:1 pour la valeur de marque par défaut, spec 18
 * §5.5) et `text-on-primary` (jeton du vocabulaire depuis le n° 145, jusque-là
 * jamais consommé : le texte restait codé en dur en `text-white`). `danger`
 * garde `text-white` codé en dur : la gamme `danger` est déjà validée AA par
 * la règle 3 du spec 18 §13.4 sur ses paliers 500-600, seul `primary` est visé
 * par le calcul du §5.5 — voir suivi n° 368.
 */
final class Button extends Component
{
    /** @var array<string, string> */
    private const VARIANTS = [
        'primary' => 'bg-primary-strong text-on-primary hover:opacity-90',
        'secondary' => 'border border-border bg-surface text-foreground hover:bg-surface-subtle',
        'danger' => 'bg-danger text-white hover:opacity-90',
        'ghost' => 'text-foreground hover:bg-surface-subtle',
    ];

    /** @var array<string, string> */
    private const SIZES = [
        'sm' => 'gap-1.5 px-2 py-1.5',
        'default' => 'gap-2 px-3 py-2',
    ];

    private const BASE = 'inline-flex items-center rounded-md text-sm font-medium disabled:cursor-not-allowed disabled:opacity-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:ring-offset-surface-subtle';

    public string $classes;

    public function __construct(
        public string $variant = 'primary',
        public string $size = 'default',
        public ?string $href = null,
        public string $type = 'button',
    ) {
        $this->classes = self::BASE.' '
            .(self::SIZES[$this->size] ?? self::SIZES['default']).' '
            .(self::VARIANTS[$this->variant] ?? self::VARIANTS['primary']);
    }

    public function render(): View
    {
        return view('baobab::components.button');
    }
}
