@props([
    'name',
    'label' => null,
    'value' => null,
])

{{--
    `JsonField::cast()` déclare `array` : la valeur arrive décodée. L'encodage
    appartient donc au composant, faute de quoi une saisie rendrait `Array` —
    la fiche de Content Type le fait aujourd'hui en amont, dans son
    contrôleur (`json_value`), ce qui oblige chaque appelant à préparer la
    valeur au lieu de passer le champ.

    Le chemin retour (chaîne saisie → tableau validé) ne peut pas vivre ici :
    il se joue avant la validation, dans la requête.
--}}
<x-baobab::field.textarea
    :name="$name"
    :label="$label"
    :value="is_string($value) || $value === null ? $value : json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)"
    rows="6"
    {{ $attributes }}
/>
