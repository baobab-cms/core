@if (! empty($items))
    <div {{ $attributes }}>
        @foreach ($items as $item)
            <div class="widget widget-{{ $item['widget_key'] }}">
                @include($item['view'], ['data' => $item['data']])
            </div>
        @endforeach
    </div>
@endif
