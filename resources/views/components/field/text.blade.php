@props([
    'name',
    'label' => null,
    'value' => null,
    'type' => 'text',
])

<div class="mb-4">
    @if ($label)
        <label for="{{ $name }}" class="mb-1 block text-sm font-medium text-foreground">{{ $label }}</label>
    @endif

    <input
        type="{{ $type }}"
        id="{{ $name }}"
        name="{{ $name }}"
        value="{{ old($name, $value) }}"
        aria-invalid="{{ $errors->has($name) ? 'true' : 'false' }}"
        @if ($errors->has($name)) aria-describedby="{{ $name }}-error" @endif
        {{ $attributes->class([
            'w-full rounded-md border px-3 py-2 text-sm text-foreground',
            'border-danger' => $errors->has($name),
            'border-border' => ! $errors->has($name),
        ]) }}
    >

    <x-baobab::field.error :name="$name" />
</div>
