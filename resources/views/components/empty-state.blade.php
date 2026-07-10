@props([
    'message' => __('baobab::admin.components.no_results'),
])

<div {{ $attributes->class(['flex flex-col items-center gap-3 px-6 py-12 text-center']) }}>
    <p class="text-sm text-muted">{{ $message }}</p>

    @isset($action)
        <div>{{ $action }}</div>
    @endisset
</div>
