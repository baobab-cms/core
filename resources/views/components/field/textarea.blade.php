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
        aria-invalid="{{ $errors->has($name) ? 'true' : 'false' }}"
        @if ($errors->has($name)) aria-describedby="{{ $name }}-error" @endif
        {{ $attributes->class([
            'w-full rounded-md border px-3 py-2 text-sm text-foreground',
            'border-danger' => $errors->has($name),
            'border-border' => ! $errors->has($name),
        ]) }}
    >{{ old($name, $value) }}</textarea>

    <x-baobab::field.error :name="$name" />
</div>
