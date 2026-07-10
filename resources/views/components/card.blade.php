<div {{ $attributes->class(['overflow-hidden rounded-lg border border-border bg-surface']) }}>
    @isset($header)
        <div class="border-b border-border px-4 py-3">{{ $header }}</div>
    @endisset

    <div class="p-4">
        {{ $slot }}
    </div>

    @isset($footer)
        <div class="border-t border-border bg-surface-subtle px-4 py-3">{{ $footer }}</div>
    @endisset
</div>
