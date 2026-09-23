@props([
    'name',
    'label' => null,
    'value' => null,
    'type' => 'text',
    'bag' => 'default',
    'id' => null,
])

<div class="mb-4">
    @if ($label)
        <label for="{{ $id ?? $name }}" class="mb-1 block text-sm font-medium text-foreground">{{ $label }}</label>
    @endif

    <input
        type="{{ $type }}"
        id="{{ $id ?? $name }}"
        name="{{ $name }}"
        value="{{ old($name, $value) }}"
        aria-invalid="{{ $errors->getBag($bag)->has($name) ? 'true' : 'false' }}"
        @if ($errors->getBag($bag)->has($name)) aria-describedby="{{ $id ?? $name }}-error" @endif
        {{ $attributes->class([
            'w-full rounded-md border px-3 py-2 text-sm text-foreground',
            'border-danger' => $errors->getBag($bag)->has($name),
            'border-border' => ! $errors->getBag($bag)->has($name),
        ]) }}
    >

    <x-baobab::field.error :name="$name" :bag="$bag" :id="$id" />
</div>
