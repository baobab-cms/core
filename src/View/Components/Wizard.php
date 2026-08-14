<?php

declare(strict_types=1);

namespace Baobab\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * `<x-baobab::wizard :steps="…">` — navigation par étapes (spec-admin §4.1,
 * vague 2, suivi n° 6), utilisée par le Studio et reprise par l'installateur
 * (M8 point 3).
 *
 * **Ce que cette conversion corrige, et ce qu'elle ne corrige pas.** Contrôlé
 * le 14 août 2026, cette vue ne portait **aucun** bloc `@php` — contrairement
 * à ce qu'affirmait le suivi n° 138, dont l'inventaire avait pris pour tel le
 * commentaire de la vue qui disait précisément le contraire. Son `$steps` est
 * bien précalculé par le contrôleur appelant (`StudioController::navSteps()`).
 *
 * Restait de la décision malgré tout, dans une autre syntaxe : les classes de
 * chaque étape étaient choisies par des ternaires écrits dans les attributs.
 * Elles sont ici deux méthodes, testables et lisibles une fois pour toutes.
 *
 * `text-white`/`bg-white` sont conservés — voir la note de `Button` : la spec
 * 18 §13.4 ne les proscrit pas, et aucun token n'exprime « premier plan sur
 * primary ».
 */
final class Wizard extends Component
{
    /**
     * @param  array<int, array<string, mixed>>  $steps
     */
    public function __construct(public array $steps = []) {}

    /**
     * @param  array<string, mixed>  $step
     */
    public function linkClasses(array $step): string
    {
        return $this->isCurrent($step)
            ? 'bg-primary text-white'
            : 'bg-surface-subtle text-foreground hover:bg-sand-100';
    }

    /**
     * @param  array<string, mixed>  $step
     */
    public function markerClasses(array $step): string
    {
        return $this->isCurrent($step) ? 'text-primary' : 'text-foreground';
    }

    /**
     * @param  array<string, mixed>  $step
     */
    public function isCurrent(array $step): bool
    {
        return ($step['status'] ?? null) === 'current';
    }

    /**
     * @param  array<string, mixed>  $step
     */
    public function isCompleted(array $step): bool
    {
        return ($step['status'] ?? null) === 'completed';
    }

    public function render(): View
    {
        return view('baobab::components.wizard');
    }
}
