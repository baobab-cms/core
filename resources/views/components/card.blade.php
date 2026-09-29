@props(['title' => null])

<div {{ $attributes->class(['overflow-hidden rounded-lg border border-border bg-surface']) }}>
    @if (isset($header))
        <div class="border-b border-border px-4 py-2">{{ $header }}</div>
    @elseif ($title)
        <div class="border-b border-border px-4 py-2">
            <h3 class="font-display text-lg font-medium leading-tight text-foreground">{{ $title }}</h3>
        </div>
    @endif

    <div class="p-4">
        {{ $slot }}
    </div>

    @isset($footer)
        <div class="border-t border-border bg-surface-subtle px-4 py-2">{{ $footer }}</div>
    @endisset
</div>
