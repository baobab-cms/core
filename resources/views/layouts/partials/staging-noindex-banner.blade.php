@if ($stagingNoindexActive ?? false)
    {{--
        Paliers 50/700, jamais d'opacité — règle tranchée deux fois ce jalon
        (n° 84, n° 205, spec 18 §13.4 règle 2), déjà suivie par
        `<x-baobab::toasts>` et `<x-baobab::badge>` (M9 point 5, Pass A,
        suivi n° 366).
    --}}
    <div class="border-b border-warning-200 bg-warning-50 px-4 py-2 text-center text-sm text-warning-700">
        {{ __('baobab::admin.staging_banner.message') }}
    </div>
@endif
