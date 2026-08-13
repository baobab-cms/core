@props([
    'name',
    'label' => null,
    'value' => null,
])

{{--
    `step="any"` plutôt qu'un pas dérivé du `scale` déclaré : le composant ne
    reçoit pas les options du champ, et un pas trop grossier ferait rejeter par
    le navigateur une valeur que la colonne accepte. La précision reste gardée
    par les règles de validation, qui, elles, connaissent le `scale`.
--}}
<x-baobab::field.text
    :name="$name"
    :label="$label"
    :value="$value"
    type="number"
    step="any"
    {{ $attributes }}
/>
