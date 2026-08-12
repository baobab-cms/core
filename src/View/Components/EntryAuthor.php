<?php

declare(strict_types=1);

namespace Baobab\View\Components;

use Baobab\Users\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\View\Component;

/**
 * `<x-baobab::entry-author :entry="$entry" />` (spec 19 §5.4) — l'auteur d'une
 * entrée, quand elle en a un.
 *
 * **Pourquoi un composant à part, et non un cinquième rôle de `field.auto`.**
 * `author_id` est une **colonne de convention** (spec 02 §4.2), pas un champ
 * du blueprint : le faire entrer dans un composant nommé `field.*` obligerait
 * à mentir sur ce qu'il est, et le rôle n'aurait rien à résoudre dans le
 * catalogue de champs.
 *
 * **Pourquoi la résolution est ici et non dans le modèle généré.** Le
 * `model.stub` ne produit aucune relation `author()` : lui en ajouter une ne
 * servirait que les types générés **ensuite**, jamais ceux déjà installés — et
 * la régénération des modules existants est elle-même bloquée (suivi n° 69).
 * Résoudre côté Core fait fonctionner tous les types, anciens compris, sans
 * rien régénérer.
 *
 * Rendu en **texte, sans lien** : le Core n'expose aucune page d'archive par
 * auteur, et en fabriquer une ici serait inventer une route que rien ne sert.
 */
final class EntryAuthor extends Component
{
    public ?User $author = null;

    public function __construct(Model $entry)
    {
        $authorId = $entry->getAttribute('author_id');

        if (is_numeric($authorId)) {
            $this->author = User::find((int) $authorId);
        }
    }

    public function render(): View
    {
        return view('baobab::components.entry-author');
    }
}
