@props([
    'variant' => 'neutral',
])

@php
    $variants = [
        'neutral' => 'bg-surface-subtle text-muted',
        'success' => 'bg-success/10 text-success',
        'warning' => 'bg-warning/10 text-warning',
        'danger' => 'bg-danger/10 text-danger',
    ];
@endphp

<span {{ $attributes->class([
    'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium',
    $variants[$variant] ?? $variants['neutral'],
]) }}>
    {{ $slot }}
</span>
