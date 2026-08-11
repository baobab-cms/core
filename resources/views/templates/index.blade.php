{{--
    Repli universel du Core (spec 19 §6.5).

    Atteint de trois façons différentes selon le contexte qui y retombe :
    avec une entrée (`$entry` + `$title`), avec une liste (`$entries` +
    `$title`), ou sans rien. Il absorbe les trois plutôt que d'en supposer
    une seule — c'est la définition d'un repli universel.

    Il ne dit **jamais** qu'aucun thème n'est actif : ce diagnostic s'adresse
    à un administrateur, et il vit dans la bande admin.
--}}
@extends('baobab::layouts.public')

@section('content')
    <h1 class="bb-title">{{ $title ?? __('baobab::rendering.index_title') }}</h1>

    @isset($entries)
        @if (count($entries) > 0)
            <ul class="bb-list">
                @foreach ($entries as $item)
                    <li>{{ $item->getAttribute('slug') }}</li>
                @endforeach
            </ul>

            {{ $entries->links() }}
        @else
            <p class="bb-muted">{{ __('baobab::rendering.archive_empty') }}</p>
        @endif
    @else
        @empty($title)
            <p class="bb-muted">{{ __('baobab::rendering.index_body') }}</p>
        @endempty
    @endisset
@endsection
