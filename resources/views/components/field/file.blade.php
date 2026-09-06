@props([
    'name',
    'label' => null,
])

{{--
    Champ fichier des formulaires (spec 14 §5, Pass C3) — distinct de
    `field.media` (Content Types, sélectionne un média déjà présent dans la
    médiathèque publique) : ceci est une vraie saisie native `<input
    type="file">`, un envoi neuf à chaque soumission, jamais un renvoi vers
    un fichier existant.

    Pas de `value`/`old()` : un navigateur refuse de préremplir un champ
    fichier (restriction de sécurité universelle, pas une limite de ce
    composant) — une soumission rejetée oblige à resélectionner le fichier.
--}}
<div class="mb-4">
    @if ($label)
        <label for="{{ $name }}" class="mb-1 block text-sm font-medium text-foreground">{{ $label }}</label>
    @endif

    <input
        type="file"
        id="{{ $name }}"
        name="{{ $name }}"
        {{ $attributes->class([
            'block w-full text-sm text-foreground file:mr-3 file:rounded-md file:border-0 file:bg-surface file:px-3 file:py-2 file:text-sm',
            'border-danger' => $errors->has($name),
        ]) }}
    >

    @error($name)
        <p class="mt-1 text-xs text-danger">{{ $message }}</p>
    @enderror
</div>
