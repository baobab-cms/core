@props([
    'name',
    'label' => null,
    'value' => false,
])

{{--
    Résout l'orphelin relevé au n° 117 : `checkbox.blade.php` existait sans
    qu'aucun type de champ ne le déclare, pendant que `BooleanField` déclarait
    un `field.boolean` qui n'existait pas — les deux moitiés du même
    malentendu. `checkbox` devient la primitive (une case, un `checked`),
    `boolean` le composant typé qui suit le contrat commun `name`/`label`/
    `value` des autres champs. Sans cette couche, chaque gabarit générateur
    devait traiter le booléen à part, ce que faisait `admin-form.stub`.
--}}
<x-baobab::field.checkbox
    :name="$name"
    :label="$label"
    :checked="(bool) $value"
    {{ $attributes }}
/>
