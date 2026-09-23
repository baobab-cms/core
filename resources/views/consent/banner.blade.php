{{--
    Bannière de consentement par défaut du Core (spec 16 §3.2).

    Minimaliste et sans dark pattern : « Tout refuser » et « Tout accepter »
    ont exactement le même poids visuel, le détail par catégorie est à un
    clic. Rendue avec `hidden` : c'est `consent.js` qui l'affiche quand aucun
    choix valide n'existe — sans script, rien ne s'affiche et aucun script
    conditionné ne s'exécute.

    CSS inline sur les tokens `--bb-*`, comme la bande admin et le layout des
    replis : un thème tiers peut ne pas avoir Tailwind, la bannière doit
    rester correcte quoi qu'il arrive. Le comportement ne passe que par les
    attributs `data-baobab-consent-*` (contrat en tête de `consent.js`).
--}}
<style>
    .bb-consent {
        position: fixed;
        inset: auto var(--bb-spacing-4, 1rem) var(--bb-spacing-4, 1rem) var(--bb-spacing-4, 1rem);
        z-index: 999998;
        max-width: 40rem;
        margin: 0 auto;
        padding: var(--bb-spacing-6, 1.5rem);
        background: var(--bb-color-background, #fff);
        color: var(--bb-color-text, #1a1a1a);
        border: 1px solid var(--bb-color-border, #e5e5e3);
        border-radius: var(--bb-radius-lg, .75rem);
        font-family: var(--bb-font-body, system-ui, sans-serif);
        font-size: var(--bb-text-sm, .875rem);
        line-height: var(--bb-leading-relaxed, 1.65);
    }

    .bb-consent[hidden], .bb-consent [hidden] { display: none; }

    .bb-consent h2 {
        margin: 0 0 var(--bb-spacing-2, .5rem);
        font-size: var(--bb-text-base, 1rem);
        font-weight: var(--bb-weight-semibold, 600);
    }

    .bb-consent p { margin: 0; }

    .bb-consent-panel {
        margin-top: var(--bb-spacing-4, 1rem);
        padding: 0;
        border: 0;
    }

    .bb-consent-row {
        display: grid;
        grid-template-columns: auto 1fr;
        gap: 0 var(--bb-spacing-2, .5rem);
        padding: var(--bb-spacing-2, .5rem) 0;
        border-top: 1px solid var(--bb-color-border, #e5e5e3);
    }

    .bb-consent-row label { font-weight: var(--bb-weight-semibold, 600); }

    .bb-consent-row p { grid-column: 2; color: var(--bb-color-muted, #6b6b66); }

    .bb-consent-actions {
        display: flex;
        flex-wrap: wrap;
        gap: var(--bb-spacing-2, .5rem);
        margin-top: var(--bb-spacing-4, 1rem);
    }

    .bb-consent button {
        padding: var(--bb-spacing-2, .5rem) var(--bb-spacing-4, 1rem);
        border: 1px solid var(--bb-color-text, #1a1a1a);
        border-radius: var(--bb-radius-md, .375rem);
        background: var(--bb-color-background, #fff);
        color: var(--bb-color-text, #1a1a1a);
        font: inherit;
        cursor: pointer;
    }

    .bb-consent-sr {
        position: absolute;
        width: 1px;
        height: 1px;
        overflow: hidden;
        clip: rect(0 0 0 0);
        white-space: nowrap;
    }

    .bb-consent :focus-visible { outline: 2px solid var(--bb-color-primary, #1a1a1a); outline-offset: 2px; }
</style>

<div
    class="bb-consent"
    data-baobab-consent-banner
    hidden
    role="dialog"
    aria-modal="false"
    aria-labelledby="bb-consent-title"
    aria-describedby="bb-consent-intro"
>
    <h2 id="bb-consent-title">{{ __('baobab::privacy.cookies.banner.title') }}</h2>
    <p id="bb-consent-intro">{{ __('baobab::privacy.cookies.banner.intro') }}</p>

    <fieldset class="bb-consent-panel" id="bb-consent-panel" data-baobab-consent-panel hidden>
        <legend class="bb-consent-sr">{{ __('baobab::privacy.cookies.banner.customize') }}</legend>

        <div class="bb-consent-row">
            <input type="checkbox" id="bb-consent-necessary" checked disabled>
            <label for="bb-consent-necessary">{{ $necessary['label'] }} — {{ __('baobab::privacy.cookies.banner.always_active') }}</label>
            <p>{{ $necessary['description'] }}</p>
        </div>

        @foreach ($categories as $category)
            <div class="bb-consent-row">
                <input type="checkbox" id="bb-consent-{{ $category['key'] }}" data-baobab-consent-category="{{ $category['key'] }}">
                <label for="bb-consent-{{ $category['key'] }}">{{ $category['label'] }}</label>
                <p>{{ $category['description'] }}</p>
            </div>
        @endforeach

        <div class="bb-consent-actions">
            <button type="button" data-baobab-consent-action="save">{{ __('baobab::privacy.cookies.banner.save') }}</button>
        </div>
    </fieldset>

    <div class="bb-consent-actions">
        <button type="button" data-baobab-consent-action="reject-all">{{ __('baobab::privacy.cookies.banner.reject_all') }}</button>
        <button type="button" data-baobab-consent-action="accept-all">{{ __('baobab::privacy.cookies.banner.accept_all') }}</button>
        <button type="button" data-baobab-consent-action="customize" aria-expanded="false" aria-controls="bb-consent-panel">{{ __('baobab::privacy.cookies.banner.customize') }}</button>
    </div>
</div>
