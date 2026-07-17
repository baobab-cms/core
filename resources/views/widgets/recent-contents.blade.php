@if (! empty($data['items']))
    <ul>
        @foreach ($data['items'] as $item)
            <li>
                <a href="{{ $item['url'] }}">{{ $item['label'] }}</a>
                @if ($data['show_dates'] && $item['date'])
                    <time>{{ $item['date']->format('d/m/Y') }}</time>
                @endif
            </li>
        @endforeach
    </ul>
@endif
