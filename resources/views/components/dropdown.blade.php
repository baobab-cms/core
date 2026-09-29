{{--
    Panneau flottant partagé (`direction-visuelle.md` §8.6 : surface, bordure,
    rayon md, ombre sm, 200 px min) — extrait des trois panneaux dupliqués de
    `admin-topbar.blade.php` (notifications, raccourcis, compte), qui
    utilisaient `shadow-lg` (l'ombre de la modale) au lieu de `shadow-sm`
    (suivi n° 376 décision 2).

    Ne porte que le panneau, pas le déclencheur ni le conteneur `relative` :
    l'état ouvert/fermé reste dans le scope Alpine ambiant de l'appelant
    (`state`, le nom littéral de la variable booléenne — `notificationCenter()`
    porte d'autres données que ce panneau ne connaît pas, un `x-data` propre
    au composant casserait ce scope partagé).
--}}
@props(['state' => 'open', 'width' => 'w-64'])

<div
    x-show="{{ $state }}"
    x-on:click.outside="{{ $state }} = false"
    x-cloak
    {{ $attributes->class(['absolute right-0 top-10 z-10 rounded-md border border-border bg-surface p-2 shadow-sm', $width]) }}
>
    {{ $slot }}
</div>
