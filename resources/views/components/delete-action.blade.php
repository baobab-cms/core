{{--
    Suppression d'un élément : bouton + `<x-baobab::confirm>` qui nomme
    l'élément visé et dit ce qui disparaît, à la place du `confirm()` natif du
    navigateur (suivi n° 392, décision de séance du 4 octobre 2026). Le
    formulaire DELETE est soumis depuis la modale ; `$action` est son URL.

    `$variant` : `link` (lien texte rouge, lignes de liste) ou `button`
    (bouton danger plein, pied de page d'un écran).

    `$name` doit être unique sur la page (une modale par ligne) : il se
    construit à partir de l'identifiant de l'élément.
--}}
@props(['action', 'name', 'title', 'description', 'label', 'variant' => 'link'])

@if ($variant === 'button')
    <x-baobab::button type="button" variant="danger" x-on:click="$dispatch('open-modal', '{{ $name }}')">
        {{ $label }}
    </x-baobab::button>
@else
    <button type="button" class="text-danger hover:underline" x-on:click="$dispatch('open-modal', '{{ $name }}')">
        {{ $label }}
    </button>
@endif

<x-baobab::confirm :name="$name" :title="$title">
    <x-slot:description>{{ $description }}</x-slot:description>

    <form method="POST" action="{{ $action }}">
        @csrf
        @method('DELETE')
        @isset($fields)
            {{ $fields }}
        @endisset
        <x-baobab::button type="submit" variant="danger" class="w-full justify-center">
            {{ $label }}
        </x-baobab::button>
    </form>
</x-baobab::confirm>
