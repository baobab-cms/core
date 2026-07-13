<?php

declare(strict_types=1);

namespace Baobab\View\Components;

use Baobab\Media\Conversions\MediaVariantResolver;
use Baobab\Media\Models\Media;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * `<x-baobab::img :media="$car->photo" preset="card" />` (spec 06 §6, M4
 * point 4) : srcset/sizes responsive générés depuis les variantes, <picture>
 * avec WebP (+ AVIF si activé), lazy loading natif, alt du média surchargeable
 * par la prop, dimensions intrinsèques posées (zéro layout shift). Sans
 * preset — ou pour un SVG, un média externe (vignette) ou tout média sans
 * variantes — retombe sur un <img> simple. Ne rend rien si le média est nul
 * (champ image vide : pas de balise cassée dans le thème).
 */
final class Img extends Component
{
    public function __construct(
        public ?Media $media = null,
        public ?string $preset = null,
        public ?string $alt = null,
        public ?string $sizes = null,
        public string $loading = 'lazy',
    ) {}

    public function shouldRender(): bool
    {
        return $this->media !== null;
    }

    public function render(): View
    {
        /** @var Media $media shouldRender() garantit un média non nul */
        $media = $this->media;

        $sources = $media->isRasterImage() && $this->preset !== null
            ? app(MediaVariantResolver::class)->sources($media, $this->preset)
            : null;

        return view('baobab::components.img', [
            'src' => $sources['src'] ?? $media->url(),
            'width' => $sources['width'] ?? $media->currentWidth(),
            'height' => $sources['height'] ?? $media->currentHeight(),
            'sourceSets' => $sources['source_sets'] ?? [],
            'fallbackSrcset' => $sources['fallback_srcset'] ?? null,
            'altText' => $this->alt ?? $media->alt ?? $media->title ?? '',
            'sizesAttr' => $this->sizes ?? '100vw',
            'loading' => $this->loading,
        ]);
    }
}
