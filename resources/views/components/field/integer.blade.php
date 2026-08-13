@props([
    'name',
    'label' => null,
    'value' => null,
])

<x-baobab::field.text
    :name="$name"
    :label="$label"
    :value="$value"
    type="number"
    step="1"
    {{ $attributes }}
/>
