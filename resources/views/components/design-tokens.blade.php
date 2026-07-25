@if ($preloadHref)
    <link rel="preload" href="{{ $preloadHref }}" as="font" type="font/woff2" crossorigin>
@endif

@if ($inlineCss)
    <style>{!! $inlineCss !!}</style>
@else
    <link rel="stylesheet" href="{{ $href }}">
@endif
