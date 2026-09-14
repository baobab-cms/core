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
            aria-invalid="{{ $errors->has($name) ? 'true' : 'false' }}"
            @if ($errors->has($name)) aria-describedby="{{ $name }}-error" @endif
            {{ $attributes->class(['rounded border-border']) }}
        >
        {{ $label }}
    </label>

    <x-baobab::field.error :name="$name" />
</div>
