@props([
    'name',
    'label' => null,
    'value' => [],
    'options' => [],
])

{{--
    Le composant qui manquait au n° 117, et dont la fiche de Content Type
    portait jusqu'ici une copie écrite en clair dans son gabarit — c'est
    d'ailleurs le seul champ qu'elle rendait sans passer par un composant.

    `name="{{ $name }}[]"` : un `<select multiple>` ne soumet rien du tout
    quand aucune option n'est retenue, ce que la règle `array` du type traduit
    en « champ absent ». Le champ caché qui précède garantit un tableau vide
    plutôt qu'une absence, faute de quoi vider une sélection ne la viderait
    jamais — elle resterait telle qu'elle était.
--}}
<div class="mb-4">
    @if ($label)
        <label for="{{ $name }}" class="mb-1 block text-sm font-medium text-foreground">{{ $label }}</label>
    @endif

    <input type="hidden" name="{{ $name }}[]" value="">

    <select
        id="{{ $name }}"
        name="{{ $name }}[]"
        multiple
        aria-invalid="{{ $errors->has($name) ? 'true' : 'false' }}"
        @if ($errors->has($name)) aria-describedby="{{ $name }}-error" @endif
        {{ $attributes->class([
            'w-full rounded-md border bg-surface px-3 py-2 text-sm text-foreground',
            'border-danger' => $errors->has($name),
            'border-border' => ! $errors->has($name),
        ]) }}
    >
        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected(in_array((string) $optionValue, array_map('strval', (array) old($name, $value)), true))>
                {{ $optionLabel }}
            </option>
        @endforeach
    </select>

    <x-baobab::field.error :name="$name" />
</div>
