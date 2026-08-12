<?php

declare(strict_types=1);

namespace Baobab\View\Components;

use Baobab\ContentTypes\Models\ContentType;
use Baobab\ContentTypes\Support\FieldDisplay;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\View\Component;

/**
 * `<x-baobab::entry-link :entry="$entry" />` (spec 19 §5.5) — le titre d'une
 * entrée, lié vers sa page quand son type est adressable.
 *
 * **Le manque qu'il comble.** Un template de liste ne reçoit que des modèles :
 * il ne connaît ni le `title_field` du type, ni son préfixe d'URL. Sans ce
 * composant, une carte d'archive ne peut afficher qu'un slug brut — c'est
 * exactement ce que faisait le repli du Core avant lui — et ne peut pas lier
 * l'entrée qu'elle annonce. Une archive dont les cartes ne mènent nulle part
 * n'est pas une archive.
 *
 * Même résolution que `Auto` (`ContentType::forModelClass()`), même refus de
 * deviner : une entrée dont le Content Type est introuvable rend son slug, et
 * un type non adressable rend son titre **sans lien** plutôt qu'un lien mort.
 *
 * Le titre par défaut est celui du blueprint ; un slot le remplace lorsque
 * l'appelant a mieux (le `$title` que le Core passe déjà à `single`, par
 * exemple).
 */
final class EntryLink extends Component
{
    public string $title = '';

    public ?string $url = null;

    public function __construct(public Model $entry)
    {
        $contentType = ContentType::forModelClass($entry::class);

        $slug = $entry->getAttribute('slug');
        $this->title = is_string($slug) ? $slug : (string) $entry->getKey();

        if ($contentType === null) {
            return;
        }

        $titleField = FieldDisplay::titleKey($contentType->blueprint);
        $title = $titleField === null ? null : $entry->getAttribute($titleField);

        if (is_string($title) && $title !== '') {
            $this->title = $title;
        }

        if ($contentType->is_addressable && is_string($slug) && $slug !== '') {
            $this->url = route('baobab.public.show', ['prefix' => $contentType->urlPrefix(), 'slug' => $slug]);
        }
    }

    public function render(): View
    {
        return view('baobab::components.entry-link');
    }
}
