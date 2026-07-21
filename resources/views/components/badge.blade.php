@props([
    'variant' => 'neutral',
])

@php
    // Palier 50/600-700 (spec 18 §13.4 règle 2), pas d'opacité — contraste
    // WCAG AA visé (règle 3). `success` retombe sur `leaf`, défaut spec pour
    // ce rôle (§2.2).
    $variants = [
        'neutral' => 'bg-sand-100 text-sand-600',
        'success' => 'bg-leaf-50 text-leaf-600',
        'warning' => 'bg-warning-50 text-warning-700',
        'danger' => 'bg-danger-50 text-danger-700',
        'info' => 'bg-info-50 text-info-700',
    ];
@endphp

<span {{ $attributes->class([
    'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium',
    $variants[$variant] ?? $variants['neutral'],
]) }}>
    {{ $slot }}
</span>
