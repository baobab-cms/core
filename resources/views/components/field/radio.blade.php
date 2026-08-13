@props([
    'name',
    'label' => null,
    'value' => null,
    'options' => [],
])

{{--
    De vrais boutons radio, et non une liste déroulante : `RadioField` et
    `SelectField` ne diffèrent que par leurs composants (leur colonne, leurs
    règles et leur cast sont identiques), donc rendre un `radio` en `<select>`
    revient à n'avoir jamais eu deux types. La fiche de Content Type le fait
    encore — divergence signalée, à réduire par la fusion du point 2.
--}}
<div class="mb-4">
    @if ($label)
        <span class="mb-1 block text-sm font-medium text-foreground">{{ $label }}</span>
    @endif

    <div class="flex flex-col gap-2">
        @foreach ($options as $optionValue => $optionLabel)
            <label for="{{ $name }}_{{ $optionValue }}" class="flex items-center gap-2 text-sm text-foreground">
                <input
                    type="radio"
                    id="{{ $name }}_{{ $optionValue }}"
                    name="{{ $name }}"
                    value="{{ $optionValue }}"
                    @checked((string) old($name, $value) === (string) $optionValue)
                    {{ $attributes->class(['border-border']) }}
                >
                {{ $optionLabel }}
            </label>
        @endforeach
    </div>

    @error($name)
        <p class="mt-1 text-xs text-danger">{{ $message }}</p>
    @enderror
</div>
