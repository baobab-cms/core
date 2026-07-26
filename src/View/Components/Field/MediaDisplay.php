<?php

declare(strict_types=1);

namespace Baobab\View\Components\Field;

use Baobab\Media\Models\Media;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * `<x-baobab::field.media-display :value="$entry->photo" />` (spec 17 §4) —
 * champs `image`/`file` (`ImageField`/`FileField` partagent ce composant,
 * `displayComponent()`), qui ne stockent qu'un id de média brut sur la table
 * du Content Type (aucune relation Eloquent générée, spec 02 §3.2). La
 * résolution du média a lieu ici, dans le constructeur — jamais dans la vue,
 * patron `Menu`/`WidgetZone`. Délègue le rendu à `<x-baobab::img>`, déjà
 * complet (`<picture>`/srcset/WebP/AVIF/lazy-loading), rien de nouveau à
 * inventer côté affichage.
 */
final class MediaDisplay extends Component
{
    public ?Media $media;

    public function __construct(int|string|null $value = null)
    {
        $this->media = $value === null ? null : Media::find((int) $value);
    }

    public function render(): View
    {
        return view('baobab::components.field.media-display');
    }
}
