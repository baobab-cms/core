{{--
    CSS inline, jamais de classes Tailwind : un thème tiers peut ne pas avoir
    Tailwind, la bande doit rester correcte quoi qu'il arrive (spec 03 §1).
    Couleurs sur `var(--bb-color-*, repli)` plutôt que codées en dur — la
    palette WordPress d'origine (#1d2327 etc.) est remplacée par les tokens
    de la marque (M9 point 5, Pass A, suivi n° 366).

    Fond/texte **inversés** plutôt que lus tels quels : `text` devient le
    fond, `background` le premier plan — la seule paire dont le contraste
    est garanti (c'est la paire de lecture principale du produit, spec 18),
    le vocabulaire de tokens ne comportant aucune couleur « chrome sombre »
    (aucun mode sombre avant v1, §2.3). Le contraste reste identique à
    l'inversion, la formule WCAG étant symétrique.

    Le jaune du diagnostic (#f0c33c) reste en dur, volontairement : mesuré à
    ~8,5:1 sur le fond sombre par défaut, contre ~4,1:1 pour
    `var(--bb-color-warning)` — sous le seuil AA (4,5:1) pour du texte
    courant. Aucune paire « avertissement sur fond sombre » n'est validée
    ailleurs dans le système ; un jalon qui a fermé un audit WCAG 2.1 AA
    entier (M9 point 2) n'a pas à rouvrir un écart de contraste ici.
--}}
<div style="position: sticky; top: 0; z-index: 999999; display: flex; align-items: center; justify-content: space-between; height: 32px; padding: 0 12px; background: var(--bb-color-text, #2e2b24); color: var(--bb-color-background, #faf8f5); font: 13px/32px -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">
    <div style="display: flex; align-items: center; gap: 16px; min-width: 0;">
        <a href="{{ route('admin.dashboard') }}" style="color: var(--bb-color-background, #faf8f5); text-decoration: none; font-weight: 600; white-space: nowrap;">
            {{ __('baobab::admin.admin_bar.dashboard') }}
        </a>

        {{--
            Indicateur de préview de thème (spec 03 §7) : le mécanisme
            existait déjà (`ResolveActiveTheme`, `baobab.theme-preview.*`)
            sans aucune trace visible pour l'administrateur qui prévisualise
            (M9 point 5, Pass A, suivi n° 366).
        --}}
        @if ($previewTheme)
            <span style="white-space: nowrap;">
                {{ __('baobab::admin.admin_bar.preview_notice', ['theme' => $previewTheme->title]) }}

                <a href="{{ route('baobab.theme-preview.stop') }}" style="color: var(--bb-color-background, #faf8f5); text-decoration: underline;">
                    {{ __('baobab::admin.admin_bar.preview_stop') }}
                </a>
            </span>
        @endif

        {{--
            Diagnostic réservé à qui peut agir (spec 19 §6.5) : ce message vivait
            auparavant sur la page publique, où un visiteur le lisait sans pouvoir
            rien en faire.
        --}}
        @if ($noActiveTheme)
            <span style="color: #f0c33c; white-space: nowrap;">
                {{ __('baobab::rendering.no_active_theme_notice') }}

                @if ($canManageThemes)
                    <a href="{{ route('admin.themes.index') }}" style="color: #f0c33c;">
                        {{ __('baobab::rendering.no_active_theme_action') }}
                    </a>
                @endif
            </span>
        @endif
    </div>

    <div style="display: flex; align-items: center; gap: 12px; flex-shrink: 0;">
        <span style="color: var(--bb-color-background, #faf8f5); opacity: .75;">{{ $userName }}</span>

        <form method="POST" action="{{ route('logout') }}" style="margin: 0; line-height: 1;">
            @csrf
            <button type="submit" style="background: none; border: none; color: var(--bb-color-background, #faf8f5); font: inherit; cursor: pointer; padding: 0;">
                {{ __('baobab::admin.admin_bar.logout') }}
            </button>
        </form>
    </div>
</div>
