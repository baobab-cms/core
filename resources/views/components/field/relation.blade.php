{{--
    `<x-baobab::field.relation>` (spec 04 §5) — la saisie d'une relation
    `belongsTo`. Le champ écrit la colonne `{clé}_id` de l'entité déclarante,
    déjà générée et déjà `fillable` ; il ne manquait que de quoi la renseigner
    autrement qu'en base (suivi n° 140).

    Les options arrivent **prêtes** (`identifiant => libellé`), chargées par
    `Baobab\ContentTypes\Relations\RelationOptions` : aucune requête ici, un
    gabarit ne lit pas la base.

    Écart assumé avec la spec, consigné au n° 169 : elle décrit une sélection à
    recherche asynchrone et une création rapide en modale ; ceci est un
    `<select>`. La relation devient saisissable, ce qu'elle n'était pas — la
    recherche reste à faire.

    L'option vide est toujours offerte : une relation facultative doit pouvoir
    être vidée, et sur une relation requise c'est la validation qui refuse, avec
    un message, plutôt qu'un choix imposé par défaut au premier chargement.
--}}
@props([
    'name',
    'label' => null,
    'value' => null,
    'options' => [],
])

<div class="mb-4">
    @if ($label)
        <label for="{{ $name }}" class="mb-1 block text-sm font-medium text-foreground">{{ $label }}</label>
    @endif

    <select
        id="{{ $name }}"
        name="{{ $name }}"
        {{ $attributes->class([
            'w-full rounded-md border bg-surface px-3 py-2 text-sm text-foreground',
            'border-danger' => $errors->has($name),
            'border-border' => ! $errors->has($name),
        ]) }}
    >
        <option value="">{{ __('— Aucun —') }}</option>

        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected((string) old($name, $value) === (string) $optionValue)>
                {{ $optionLabel }}
            </option>
        @endforeach
    </select>

    @error($name)
        <p class="mt-1 text-xs text-danger">{{ $message }}</p>
    @enderror
</div>
