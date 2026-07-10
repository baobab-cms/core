@props([
    'name',
    'label' => null,
    'value' => null,
])

<div class="mb-4">
    @if ($label)
        <label for="{{ $name }}" class="mb-1 block text-sm font-medium text-foreground">{{ $label }}</label>
    @endif

    <textarea
        id="{{ $name }}"
        name="{{ $name }}"
        {{ $attributes->class([
            'w-full rounded-md border px-3 py-2 text-sm text-foreground',
            'border-danger' => $errors->has($name),
            'border-border' => ! $errors->has($name),
        ]) }}
    >{{ old($name, $value) }}</textarea>

    @error($name)
        <p class="mt-1 text-xs text-danger">{{ $message }}</p>
    @enderror
</div>
