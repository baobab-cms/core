@props([
    'name',
    'maxWidth' => 'md',
    'open' => false,
])

@php
    $maxWidths = [
        'sm' => 'max-w-sm',
        'md' => 'max-w-md',
        'lg' => 'max-w-lg',
        'xl' => 'max-w-xl',
    ];
@endphp

<div
    x-data="{ show: @js($open) }"
    x-on:open-modal.window="show = ($event.detail === '{{ $name }}')"
    x-on:keydown.escape.window="show = false"
    x-show="show"
    x-cloak
    class="fixed inset-0 z-40 flex items-center justify-center px-4"
>
    <div
        x-show="show"
        x-on:click="show = false"
        class="fixed inset-0 bg-foreground/50"
        aria-hidden="true"
    ></div>

    <div
        x-show="show"
        {{ $attributes->class(['relative w-full rounded-lg border border-border bg-surface p-6 shadow-lg', $maxWidths[$maxWidth] ?? $maxWidths['md']]) }}
    >
        {{ $slot }}
    </div>
</div>
