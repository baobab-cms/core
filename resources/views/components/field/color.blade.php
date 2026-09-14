@props([
    'name',
    'label' => null,
    'value' => null,
    'resetAction' => null,
])

{{--
    Champ de couleur (spec 18 §8). Distinct de `field.text` avec `type="color"`,
    qui étirait la pastille native sur toute la largeur au point de la réduire à
    un filet — la couleur était affichée sans être lisible (suivi n° 149).

    Ici la pastille garde une taille de pastille, et la valeur hexadécimale est
    écrite à côté : on voit la couleur *et* on peut la lire. Le bouton de
    réinitialisation, quand il est fourni, vise le formulaire dédié déclaré par
    l'écran — jamais un `<form>` imbriqué dans le formulaire principal, ce qui
    serait invalide en HTML (même raison et même patron que `x-baobab::table`).
--}}
<div class="mb-4">
    @if ($label)
        <label for="{{ $name }}" class="mb-1 block text-sm font-medium text-foreground">{{ $label }}</label>
    @endif

    <div class="flex items-center gap-3" x-data="{ hex: @js(old($name, $value)) }">
        <input
            type="color"
            id="{{ $name }}"
            name="{{ $name }}"
            value="{{ old($name, $value) }}"
            x-on:input="hex = $event.target.value"
            aria-invalid="{{ $errors->has($name) ? 'true' : 'false' }}"
            @if ($errors->has($name)) aria-describedby="{{ $name }}-error" @endif
            {{ $attributes->class([
                'h-9 w-14 shrink-0 cursor-pointer rounded-md border p-1',
                'border-danger' => $errors->has($name),
                'border-border' => ! $errors->has($name),
            ]) }}
        >

        <output
            for="{{ $name }}"
            class="font-mono text-xs uppercase text-muted"
            x-text="hex"
        >{{ old($name, $value) }}</output>

        @if ($resetAction)
            <button
                type="submit"
                form="branding-reset"
                formaction="{{ $resetAction }}"
                class="ml-auto rounded-md px-2 py-1 text-xs font-medium text-muted hover:bg-surface-subtle hover:text-foreground"
                title="{{ __('baobab::admin.branding.reset_token_hint') }}"
            >
                {{ __('baobab::admin.branding.reset_token_action') }}
            </button>
        @endif
    </div>

    <x-baobab::field.error :name="$name" />
</div>
