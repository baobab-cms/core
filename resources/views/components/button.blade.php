@props([
    'variant' => 'primary',
    'href' => null,
    'type' => 'button',
])

@php
    $variants = [
        'primary' => 'bg-primary text-white hover:opacity-90',
        'secondary' => 'border border-border bg-surface text-foreground hover:bg-surface-subtle',
        'danger' => 'bg-danger text-white hover:opacity-90',
        'ghost' => 'text-foreground hover:bg-surface-subtle',
    ];

    $classes = 'inline-flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium disabled:cursor-not-allowed disabled:opacity-50 '
        .($variants[$variant] ?? $variants['primary']);
@endphp

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
