@if (count($sourceSets) > 0)
    <picture>
        @foreach ($sourceSets as $type => $srcset)
            <source type="{{ $type }}" srcset="{{ $srcset }}" sizes="{{ $sizesAttr }}">
        @endforeach
        <img
            {{ $attributes }}
            src="{{ $src }}"
            @if ($fallbackSrcset) srcset="{{ $fallbackSrcset }}" sizes="{{ $sizesAttr }}" @endif
            alt="{{ $altText }}"
            width="{{ $width }}"
            height="{{ $height }}"
            loading="{{ $loading }}"
            decoding="async"
        >
    </picture>
@else
    <img
        {{ $attributes }}
        src="{{ $src }}"
        alt="{{ $altText }}"
        @if ($width) width="{{ $width }}" @endif
        @if ($height) height="{{ $height }}" @endif
        loading="{{ $loading }}"
        decoding="async"
    >
@endif
