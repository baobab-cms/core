<span {{ $attributes->class([
    'inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium',
    $classes,
]) }}>
    <span class="h-1.5 w-1.5 rounded-full {{ $dotClass }}" aria-hidden="true"></span>
    {{ $slot }}
</span>
