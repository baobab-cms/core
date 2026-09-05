@props([
    'name',
    'text' => '',
    'privacyUrl' => null,
    'checked' => false,
])

{{--
    Champ spécial `consent` (spec 14 §2.2) — jamais dans `FieldRegistry`
    (Content Types), propre aux formulaires. Diffère de `field.checkbox` sur
    un seul point : le libellé porte un lien vers la politique de
    confidentialité quand `privacy_url` est renseigné — `field.checkbox`
    échappe son `$label`, ce qui interdirait ce lien sans y introduire du HTML
    non échappé pour ses treize autres appelants (Content Types).
--}}
<div class="mb-4">
    <label for="{{ $name }}" class="flex items-start gap-2 text-sm text-foreground">
        <input
            type="checkbox"
            id="{{ $name }}"
            name="{{ $name }}"
            value="1"
            @checked(old($name, $checked))
            {{ $attributes->class(['mt-1 rounded border-border']) }}
        >
        <span>
            {{ $text }}
            @if ($privacyUrl)
                <a href="{{ $privacyUrl }}" target="_blank" rel="noopener noreferrer" class="underline">{{ __('baobab::rendering.form_consent_privacy_link') }}</a>
            @endif
        </span>
    </label>

    @error($name)
        <p class="mt-1 text-xs text-danger">{{ $message }}</p>
    @enderror
</div>
