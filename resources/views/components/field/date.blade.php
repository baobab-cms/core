@props([
    'name',
    'label' => null,
    'value' => null,
])

{{--
    `DateField::cast()` déclare `date` : la valeur qui arrive ici est une
    instance Carbon, pas une chaîne. Un `<input type="date">` n'accepte que
    `Y-m-d` et vide silencieusement son champ devant n'importe quoi d'autre —
    un rendu Carbon par défaut (`2026-08-13 00:00:00`) donne donc un champ
    vide, sans la moindre erreur. Le formatage appartient au composant : c'est
    le seul endroit qui connaisse le type d'input servi.
--}}
<x-baobab::field.text
    :name="$name"
    :label="$label"
    :value="$value instanceof \DateTimeInterface ? $value->format('Y-m-d') : $value"
    type="date"
    {{ $attributes }}
/>
