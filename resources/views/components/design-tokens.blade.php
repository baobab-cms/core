@if ($inlineCss)
    <style>{!! $inlineCss !!}</style>
@else
    <link rel="stylesheet" href="{{ $href }}">
@endif
