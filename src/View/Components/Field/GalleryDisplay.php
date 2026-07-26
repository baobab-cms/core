<?php

declare(strict_types=1);

namespace Baobab\View\Components\Field;

use Baobab\Media\Models\Media;
use Baobab\Media\Models\MediaUsage;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\View\Component;

/**
 * `<x-baobab::field.gallery-display :entry="$entry" field="photos" />` (spec
 * 17 §4) — un champ `gallery` ne stocke aucune colonne propre, les sélections
 * vivent dans `media_usages` avec une colonne `order` (`GalleryField`, spec
 * 02 §3.2, spec 06 §6). Résolution ici, dans le constructeur — jamais dans
 * la vue, patron `Menu`/`WidgetZone`.
 */
final class GalleryDisplay extends Component
{
    /**
     * @var Collection<int, Media>
     */
    public Collection $media;

    public function __construct(Model $entry, public string $field)
    {
        $this->media = MediaUsage::query()
            ->where('usable_type', $entry::class)
            ->where('usable_id', $entry->getKey())
            ->where('field_key', $field)
            ->orderBy('order')
            ->with('media')
            ->get()
            ->pluck('media')
            ->filter();
    }

    public function render(): View
    {
        return view('baobab::components.field.gallery-display');
    }
}
