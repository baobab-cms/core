@props([
    'name',
    'label' => null,
    'value' => null,
])

{{--
    `TimeField` ne déclare aucun cast : la valeur arrive telle que la base la
    rend, `H:i:s`. Un `<input type="time">` l'accepte à la lecture, mais ne
    renvoie que `H:i` à la soumission (les secondes n'apparaissent qu'avec un
    `step` explicite) — d'où la normalisation côté requête, sans laquelle la
    règle `date_format:H:i:s` du type refuserait toute saisie.
--}}
<x-baobab::field.text
    :name="$name"
    :label="$label"
    :value="$value instanceof \DateTimeInterface ? $value->format('H:i:s') : $value"
    type="time"
    {{ $attributes }}
/>
