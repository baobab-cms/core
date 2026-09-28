<?php

declare(strict_types=1);

namespace Baobab\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * `<x-baobab::avatar name="Jean Dupont" />` — rond d'initiales, taille et
 * couleur passées par `$attributes->class(…)` comme les autres composants
 * (`<x-baobab::page>`, `<x-baobab::badge>`), pas des props dédiées.
 *
 * Espace réservé pour une future photo de profil (M9 point 5, Pass A, suivi
 * n° 366) : `User` ne porte aujourd'hui aucun champ media pour ça — construire
 * l'upload est une brique à part, hors du retrofit de coquille de cette
 * passe. Les initiales sont donc le seul rendu, pas un repli temporaire
 * parmi d'autres.
 */
final class Avatar extends Component
{
    public string $initials;

    public function __construct(string $name)
    {
        $words = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $this->initials = collect($words)
            ->take(2)
            ->map(fn (string $word): string => mb_strtoupper(mb_substr($word, 0, 1)))
            ->implode('');
    }

    public function render(): View
    {
        return view('baobab::components.avatar');
    }
}
