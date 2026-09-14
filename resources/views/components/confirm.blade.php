@props([
    'name',
    'title' => null,
    'expectedText' => null,
    'open' => false,
])

<x-baobab::modal
    :name="$name"
    :open="$open"
    :aria-labelledby="$title ? $name.'-title' : null"
    :aria-describedby="isset($description) ? $name.'-description' : null"
>
    <div x-data="{ typed: '' }">
        @if ($title)
            <h2 id="{{ $name }}-title" class="font-display text-base font-semibold text-foreground">{{ $title }}</h2>
        @endif

        @isset($description)
            <div id="{{ $name }}-description" class="mt-2 text-sm text-muted">{{ $description }}</div>
        @endisset

        @if ($expectedText)
            <div class="mt-4">
                <label for="{{ $name }}-confirm-text" class="text-xs text-muted">
                    {{ __('baobab::admin.components.confirm_placeholder', ['text' => $expectedText]) }}
                </label>
                <input
                    type="text"
                    id="{{ $name }}-confirm-text"
                    x-model="typed"
                    placeholder="{{ $expectedText }}"
                    class="mt-1 w-full rounded-md border border-border px-2 py-1 text-sm"
                >
            </div>
        @endif

        {{--
            `<fieldset disabled>` plutôt qu'une classe `pointer-events-none`
            (suivi n° 307, constat 1) : `pointer-events-none` ne bloque que la
            souris — un bouton focusé reste activable au clavier (Entrée/Espace)
            indépendamment de cette propriété CSS. `disabled` sur un fieldset
            désactive nativement tout contrôle de formulaire descendant, y
            compris à travers un `<form>` imbriqué (le cas de tous les usages
            actuels de ce composant) : retiré de l'ordre de tabulation, non
            activable au clavier ni à la souris, et correctement annoncé par
            les technologies d'assistance — un seul mécanisme couvre les deux.
        --}}
        <fieldset
            class="mt-4 min-w-0 border-0 p-0"
            @if ($expectedText)
                x-bind:disabled="typed !== @js($expectedText)"
                x-bind:class="{ 'opacity-50': typed !== @js($expectedText) }"
            @endif
        >
            {{ $slot }}
        </fieldset>

        <div class="mt-4 flex justify-end">
            <x-baobab::button type="button" variant="ghost" x-on:click="show = false">
                {{ __('baobab::admin.components.close') }}
            </x-baobab::button>
        </div>
    </div>
</x-baobab::modal>
