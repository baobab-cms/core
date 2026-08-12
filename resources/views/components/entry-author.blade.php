{{--
    Auteur d'une entrée (spec 19 §5.4). Balisage nu, comme `field.auto` : c'est
    au thème de styler. Aucun auteur — colonne vide, ou compte supprimé — ne
    rend rien du tout, pas une mention « auteur inconnu » (§5.9).
--}}
@if ($author !== null)
    <span {{ $attributes->merge(['rel' => 'author']) }}>{{ $author->name }}</span>
@endif
