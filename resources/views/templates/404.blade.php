{{--
    Repli « introuvable » (spec 19 §6, ton fixé par §5.8).

    Sobre et factuel, sans humour ni excuse : ce qui s'est passé, un champ de
    recherche, un lien d'accueil. Une erreur ne s'excuse pas et n'est jamais
    vague (direction-visuelle §10.2).
--}}
@extends('baobab::layouts.public')

@section('content')
    <h1 class="bb-title">{{ __('baobab::rendering.not_found_title') }}</h1>

    <p>{{ __('baobab::rendering.not_found_body') }}</p>

    <form method="GET" action="{{ route('baobab.search') }}" role="search">
        <label for="bb-search">{{ __('baobab::rendering.search_label') }}</label>
        <input id="bb-search" type="search" name="q" value="">
        <button type="submit">{{ __('baobab::rendering.search_submit') }}</button>
    </form>

    <p><a href="{{ url('/') }}">{{ __('baobab::rendering.home_link') }}</a></p>
@endsection
