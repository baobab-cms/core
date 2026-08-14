<?php

declare(strict_types=1);

namespace Baobab\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * `<x-baobab::badge variant="success">` — pastille d'état.
 *
 * La table des variantes vivait dans un bloc `@php` de la vue (suivi n° 138) :
 * associer un rôle à un couple fond/texte est une décision, pas de l'affichage.
 *
 * Les paliers suivent la règle 2 de la spec 18 §13.4 — palier 600-700 sur un
 * fond 50-100, jamais une opacité, qui était le défaut corrigé au n° 84 — et
 * `success` retombe sur `leaf`, gamme que la spec §2.2 désigne pour ce rôle.
 */
final class Badge extends Component
{
    /** @var array<string, string> */
    private const VARIANTS = [
        'neutral' => 'bg-sand-100 text-sand-600',
        'success' => 'bg-leaf-50 text-leaf-600',
        'warning' => 'bg-warning-50 text-warning-700',
        'danger' => 'bg-danger-50 text-danger-700',
        'info' => 'bg-info-50 text-info-700',
    ];

    public string $classes;

    public function __construct(public string $variant = 'neutral')
    {
        $this->classes = self::VARIANTS[$this->variant] ?? self::VARIANTS['neutral'];
    }

    public function render(): View
    {
        return view('baobab::components.badge');
    }
}
