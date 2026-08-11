{{--
    Layout des **pages de repli du Core** (spec 19 §6.3).

    Servi quand aucun thème n'est actif, ou quand le thème actif ne fournit
    pas le template demandé par la hiérarchie (spec 03 §4). Ce n'est pas un
    thème et ça ne prétend pas en être un : c'est un état transitoire, qui
    doit rester présentable sans chercher à faire illusion.

    Trois exigences le gouvernent :

    - **Il tient sans feuille de style.** Le balisage est sémantique et
      ordonné — titre, contenu, pied — de sorte qu'une compilation de tokens
      qui échoue dégrade la présentation sans jamais rendre la page illisible
      (§6.7, vérifié par un test).
    - **Il consomme les tokens, jamais des valeurs en dur.** Le CSS ci-dessous
      est écrit sur les `--bb-*`, donc un profil de marque le teinte comme il
      teinte le reste (spec 18). Il est inline et non compilé : les replis ne
      dépendent d'aucun build.
    - **Il ne parle jamais technique au visiteur.** Le diagnostic « aucun
      thème actif » vit dans la bande admin, pour qui peut agir (§6.5).
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <x-baobab::seo-head />
    <x-baobab::design-tokens />

    <style>
        /*
            Volontairement court. Ce CSS habille un repli, il ne compose pas
            un thème : une mesure de lecture, un rythme vertical, des liens
            lisibles. Rien de plus.
        */
        :root { color-scheme: light; }

        body {
            margin: 0;
            background: var(--bb-color-background, #fff);
            color: var(--bb-color-text, #1a1a1a);
            font-family: var(--bb-font-body, system-ui, sans-serif);
            line-height: var(--bb-leading-relaxed, 1.65);
        }

        .bb-skip {
            position: absolute;
            left: -9999px;
            top: 0;
            padding: var(--bb-spacing-2, .5rem) var(--bb-spacing-4, 1rem);
            background: var(--bb-color-surface, #f5f5f4);
            color: var(--bb-color-text, #1a1a1a);
        }

        /* Le lien d'évitement n'apparaît qu'au clavier — mais il existe toujours dans le DOM. */
        .bb-skip:focus { left: 0; z-index: 10; }

        .bb-shell {
            max-width: 68ch;
            margin: 0 auto;
            padding: var(--bb-spacing-8, 2rem) var(--bb-spacing-4, 1rem);
        }

        .bb-title {
            font-family: var(--bb-font-display, var(--bb-font-body, system-ui, sans-serif));
            font-size: var(--bb-text-3xl, 1.9rem);
            font-weight: var(--bb-weight-semibold, 600);
            line-height: var(--bb-leading-tight, 1.2);
            margin: 0 0 var(--bb-spacing-6, 1.5rem);
            text-wrap: balance;
        }

        .bb-list { list-style: none; margin: 0; padding: 0; }

        .bb-list > li {
            padding: var(--bb-spacing-4, 1rem) 0;
            border-bottom: 1px solid var(--bb-color-border, #e5e5e3);
        }

        .bb-muted { color: var(--bb-color-muted, #6b6b66); }

        .bb-footer {
            margin-top: var(--bb-spacing-12, 3rem);
            padding-top: var(--bb-spacing-4, 1rem);
            border-top: 1px solid var(--bb-color-border, #e5e5e3);
            font-size: var(--bb-text-sm, .875rem);
        }

        a { color: inherit; }

        /* Le focus reste visible partout (plancher de qualité, direction-visuelle §14). */
        :focus-visible { outline: 2px solid var(--bb-color-primary, #1a1a1a); outline-offset: 2px; }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation-duration: .01ms !important; transition-duration: .01ms !important; }
        }
    </style>

    @stack('head')
</head>
<body>
    <a class="bb-skip" href="#bb-main">{{ __('baobab::rendering.skip_to_content') }}</a>

    <x-baobab::admin-bar />

    <main id="bb-main" class="bb-shell">
        @yield('content')
    </main>

    <footer class="bb-shell bb-footer bb-muted">
        {{ config('app.name') }}
    </footer>
</body>
</html>
