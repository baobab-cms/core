@if ($href)
    <a href="{{ $href }}" {{ $attributes->class([$classes]) }}>
        @isset($icon)<span aria-hidden="true">{{ $icon }}</span>@endisset
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->class([$classes]) }}>
        @isset($icon)<span aria-hidden="true">{{ $icon }}</span>@endisset
        {{ $slot }}
    </button>
@endif
