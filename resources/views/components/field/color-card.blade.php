@props([
    'name',
    'label',
    'value',
    'overridden' => false,
    'resetAction' => null,
])

{{--
    Une carte dont le témoin **est** le sélecteur : tout l'aplat est
    l'`input[type=color]`, cible de clic large et geste direct (décision du
    14 août 2026). L'hexadécimal affiché dessous suit la saisie en direct.

    L'input est rendu sans bordure ni fond propres et étiré sur toute la
    surface : ce qu'on voit et ce qu'on clique sont la même chose.
--}}
<div x-data="{ hex: @js(old($name, $value)) }">
    <x-baobab::token-card :label="$label" :overridden="$overridden" :reset-action="$resetAction">
        <x-slot:caption>
            <span class="font-mono text-xs uppercase text-muted" x-text="hex">{{ old($name, $value) }}</span>
        </x-slot:caption>

        <input
            type="color"
            id="{{ $name }}"
            name="{{ $name }}"
            value="{{ old($name, $value) }}"
            x-on:input="hex = $event.target.value"
            aria-label="{{ $label }}"
            aria-invalid="{{ $errors->has($name) ? 'true' : 'false' }}"
            @if ($errors->has($name)) aria-describedby="{{ $name }}-error" @endif
            class="h-full w-full cursor-pointer border-0 bg-transparent p-0"
        >
    </x-baobab::token-card>

    <x-baobab::field.error :name="$name" />
</div>
