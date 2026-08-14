@props([
    'label',
    'value' => null,
    'overridden' => false,
    'resetAction' => null,
])

{{--
    Carte de token de marque (spec 18 §8, suivi n° 149). Le témoin occupe la
    moitié haute et vient du slot : un aplat de couleur pour les couleurs, un
    carré auquel le rayon, l'espacement ou l'ombre est réellement appliqué pour
    les surfaces. Même gabarit partout — l'écran ne présentait plus ses tokens
    de deux manières différentes, ce qui était le reproche d'origine.

    Le contour marque un token **surchargé** par rapport au profil appliqué (ou
    au thème) : il n'est pas décoratif, il dit ce qui a été touché. Pour la
    même raison « Réinitialiser » n'apparaît que là — ailleurs il n'aurait
    aucun effet.
--}}
<div {{ $attributes->class([
    'flex flex-col overflow-hidden rounded-lg border bg-surface',
    'border-primary ring-1 ring-primary' => $overridden,
    'border-border' => ! $overridden,
]) }}>
    <div class="flex h-20 items-center justify-center bg-surface-subtle">
        {{ $slot }}
    </div>

    <div class="flex flex-1 flex-col gap-0.5 px-3 py-2">
        <span class="text-sm font-medium text-foreground">{{ $label }}</span>

        {{-- `caption` remplace entièrement la ligne de valeur : un affichage
             qui suit une saisie en direct (couleurs) ou un champ éditable
             (échelle, surfaces). Le composant n'a pas à connaître lequel. --}}
        @isset($caption)
            {{ $caption }}
        @else
            <span class="font-mono text-xs uppercase text-muted">{{ $value }}</span>
        @endisset
    </div>

    @if ($overridden && $resetAction)
        <button
            type="submit"
            form="branding-reset"
            formaction="{{ $resetAction }}"
            class="border-t border-border px-3 py-1.5 text-xs font-medium text-muted hover:bg-surface-subtle hover:text-foreground"
            title="{{ __('baobab::admin.branding.reset_token_hint') }}"
        >
            {{ __('baobab::admin.branding.reset_token_action') }}
        </button>
    @endif
</div>
