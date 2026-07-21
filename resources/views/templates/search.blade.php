<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <x-baobab::seo-head />
</head>
<body>
    <h1>{{ __('baobab::admin.search.public_title', ['query' => $query]) }}</h1>

    <form method="GET" action="{{ route('baobab.search') }}">
        <input type="search" name="q" value="{{ $query }}">
        <button type="submit">{{ __('baobab::admin.search.public_submit') }}</button>
    </form>

    @forelse ($results as $group)
        <h2>{{ $group['label'] }}</h2>
        <ul>
            @foreach ($group['results']->items as $item)
                <li><a href="{{ $item->url }}">{{ $item->title }}</a></li>
            @endforeach
        </ul>
    @empty
        @if ($query !== '')
            <p>{{ __('baobab::admin.search.public_empty') }}</p>
        @endif
    @endforelse
</body>
</html>
