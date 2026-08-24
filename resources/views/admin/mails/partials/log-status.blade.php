{{--
    Aucune logique ici : `MailLogStatus` porte son libellé et sa variante,
    pour la raison qui a sorti la table des variantes de `<x-badge>` (n° 138).

    La raison de l'échec est rendue sous la pastille plutôt que dans une
    colonne à elle : elle ne concerne qu'une ligne sur cent, et lui donner une
    colonne vide partout ailleurs coûterait de la largeur à ce qu'on lit
    toujours. Le `title` donne le message entier, que la troncature coupe.
--}}
<x-baobab::badge :variant="$entry->status->badgeVariant()">{{ $entry->status->label() }}</x-baobab::badge>

@if ($entry->error !== null)
    <p class="mt-1 max-w-xs truncate text-xs text-muted" title="{{ $entry->error }}">{{ $entry->error }}</p>
@endif
