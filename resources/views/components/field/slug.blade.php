@props([
    'name',
    'label' => null,
    'value' => null,
])

{{--
    Une saisie texte ordinaire — le slug n'a pas d'input HTML propre. Il a en
    revanche une contrainte que l'utilisateur ne devine pas (`alpha_dash`),
    d'où le gabarit annoncé plutôt que découvert au premier refus.
--}}
<x-baobab::field.text
    :name="$name"
    :label="$label"
    :value="$value"
    placeholder="mon-titre-de-page"
    {{ $attributes }}
/>
