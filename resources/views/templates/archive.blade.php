{{--
    Repli d'archive (spec 19 §6).

    Porte un **état vide qui parle** (§5.9) : une archive sans publication
    invite, elle ne disparaît pas en silence. C'est la règle des vides de
    contenu, par opposition aux vides de chrome.
--}}
@extends('baobab::layouts.public')

@section('content')
    <h1 class="bb-title">{{ $title }}</h1>

    @if (count($entries) > 0)
        <ul class="bb-list">
            @foreach ($entries as $entry)
                <li>{{ $entry->getAttribute('slug') }}</li>
            @endforeach
        </ul>

        {{ $entries->links() }}
    @else
        <p class="bb-muted">{{ __('baobab::rendering.archive_empty') }}</p>
    @endif
@endsection
