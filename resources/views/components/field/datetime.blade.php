@props([
    'name',
    'label' => null,
    'value' => null,
])

{{--
    Même raison que `field.date` : `DateTimeField::cast()` déclare `datetime`,
    et `<input type="datetime-local">` exige `Y-m-d\TH:i`. Le `T` séparateur
    n'est pas un détail de style, c'est ce que l'input reconnaît.
--}}
<x-baobab::field.text
    :name="$name"
    :label="$label"
    :value="$value instanceof \DateTimeInterface ? $value->format('Y-m-d\TH:i') : $value"
    type="datetime-local"
    {{ $attributes }}
/>
