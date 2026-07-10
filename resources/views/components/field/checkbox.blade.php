@props([
    'name',
    'label' => null,
    'checked' => false,
])

<div class="mb-4">
    <label for="{{ $name }}" class="flex items-center gap-2 text-sm text-foreground">
        <input
            type="checkbox"
            id="{{ $name }}"
            name="{{ $name }}"
            value="1"
            @checked(old($name, $checked))
            {{ $attributes->class(['rounded border-border']) }}
        >
        {{ $label }}
    </label>

    @error($name)
        <p class="mt-1 text-xs text-danger">{{ $message }}</p>
    @enderror
</div>
