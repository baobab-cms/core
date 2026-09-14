<?php

declare(strict_types=1);

namespace Baobab\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * `<x-baobab::modal name="confirm-delete">` — boîte modale pilotée par Alpine,
 * ouverte par l'événement `open-modal` portant son nom.
 *
 * La table des largeurs vivait dans un bloc `@php` de la vue (suivi n° 138) :
 * traduire un palier nommé en classe utilitaire est une décision.
 */
final class Modal extends Component
{
    /** @var array<string, string> */
    private const MAX_WIDTHS = [
        'sm' => 'max-w-sm',
        'md' => 'max-w-md',
        'lg' => 'max-w-lg',
        'xl' => 'max-w-xl',
    ];

    public string $maxWidthClass;

    public function __construct(
        public string $name,
        string $maxWidth = 'md',
        public bool $open = false,
        public ?string $ariaLabel = null,
        public ?string $ariaLabelledby = null,
        public ?string $ariaDescribedby = null,
    ) {
        $this->maxWidthClass = self::MAX_WIDTHS[$maxWidth] ?? self::MAX_WIDTHS['md'];
    }

    public function render(): View
    {
        return view('baobab::components.modal');
    }
}
