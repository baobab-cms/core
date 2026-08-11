{{--
    Repli de recherche (spec 19 §6, règle des deux états de §5.7).

    Deux situations distinctes, souvent confondues : **sans requête**, le
    champ seul avec une invitation ; **avec une requête sans résultat**, la
    requête rappelée et une piste de reformulation. Jamais un cul-de-sac
    silencieux.
--}}
@extends('baobab::layouts.public')

@section('content')
    <h1 class="bb-title">
        {{ $query === ''
            ? __('baobab::rendering.search_title')
            : __('baobab::rendering.search_results_title', ['query' => $query]) }}
    </h1>

    <form method="GET" action="{{ route('baobab.search') }}" role="search">
        <label for="bb-search">{{ __('baobab::rendering.search_label') }}</label>
        <input id="bb-search" type="search" name="q" value="{{ $query }}">
        <button type="submit">{{ __('baobab::rendering.search_submit') }}</button>
    </form>

    @forelse ($results as $group)
        <h2>{{ $group['label'] }}</h2>
        <ul class="bb-list">
            @foreach ($group['results']->items as $item)
                <li><a href="{{ $item->url }}">{{ $item->title }}</a></li>
            @endforeach
        </ul>
    @empty
        <p class="bb-muted">
            {{ $query === ''
                ? __('baobab::rendering.search_invite')
                : __('baobab::rendering.search_empty', ['query' => $query]) }}
        </p>
    @endforelse
@endsection
