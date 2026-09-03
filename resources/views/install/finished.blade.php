{{--
    L'écran final — **une page autonome, et c'est la décision de la passe**
    (arbitrage B1, suivi n° 235).

    Cette page est rendue par la requête qui vient d'exécuter
    `FinalizeInstallation`, laquelle a supprimé `public/baobab/install/` (§6.3).
    `wizard.css` **n'existe plus** au moment où le navigateur la lit : une page
    qui la référencerait s'afficherait nue, précisément sur l'écran qui doit
    rassurer. Le défaut n'existait pas avant la Pass C3a, où la destruction
    visait un répertoire inexistant et la feuille survivait à tout ; rendre
    l'autodestruction réelle l'a exposé.

    D'où le `<style>` en ligne, qui reprend le strict nécessaire de
    `wizard.css` : variables de marque, carte, en-tête, bouton. La duplication
    est assumée et bornée — une page de fin ne bouge plus, et l'alternative
    serait un fichier que cette même requête a détruit.

    **Aucun `@extends` du layout du wizard** pour la même raison : c'est lui
    qui porte le `<link>` vers la feuille disparue.
--}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Installation terminée — Baobab</title>

    <style>
        :root {
            --bb-green: #1E7A54;
            --bb-green-dark: #17603F;
            --bb-gold: #E9A13B;
            --bb-fg: #16211C;
            --bb-muted: #5C6B64;
            --bb-bg: #F4F6F5;
            --bb-surface: #FFFFFF;
            --bb-border: #DCE3DF;
            --bb-danger: #A3271F;
            --bb-radius: 10px;
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --bb-green: #2F9B6E;
                --bb-green-dark: #268159;
                --bb-fg: #E8EFEA;
                --bb-muted: #9AAAA2;
                --bb-bg: #101713;
                --bb-surface: #182420;
                --bb-border: #2B3A33;
                --bb-danger: #E4796F;
            }
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 2rem 1rem;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--bb-bg);
            color: var(--bb-fg);
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            font-size: 16px;
            line-height: 1.55;
        }

        .shell {
            width: 100%;
            max-width: 44rem;
            background: var(--bb-surface);
            border: 1px solid var(--bb-border);
            border-radius: var(--bb-radius);
            padding: 2rem;
            box-shadow: 0 1px 2px rgb(0 0 0 / 6%), 0 8px 24px rgb(0 0 0 / 6%);
        }

        .shell__head {
            margin-bottom: 1.75rem;
            padding-bottom: 1.25rem;
            border-bottom: 1px solid var(--bb-border);
        }

        .brand {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 700;
            letter-spacing: -0.015em;
            color: var(--bb-green);
        }

        .brand__sub {
            margin: 0.25rem 0 0;
            font-size: 0.9375rem;
            color: var(--bb-muted);
        }

        .lede {
            margin: 0 0 1rem;
        }

        .hint {
            margin: 0 0 1.5rem;
            font-size: 0.875rem;
            color: var(--bb-muted);
        }

        /*
            Même puce neutre que dans la checklist : un gris translucide se
            pose sur un fond clair comme sur un fond sombre, là où un token de
            fond s'inverse avec le thème — c'est ce qui avait rendu illisibles
            les blocs à copier, vu en recette le 3 septembre 2026.
        */
        code {
            padding: 0.08rem 0.3rem;
            border-radius: 5px;
            background: rgb(127 127 127 / 22%);
            font-family: ui-monospace, SFMono-Regular, "SF Mono", Menlo, Consolas, monospace;
            font-size: 0.8125rem;
            overflow-wrap: anywhere;
        }

        .actions {
            display: flex;
            gap: 0.75rem;
            align-items: center;
            margin: 1.5rem 0;
        }

        .button {
            padding: 0.625rem 1rem;
            background: var(--bb-green);
            color: #fff;
            border: 0;
            border-radius: 8px;
            font: inherit;
            font-weight: 600;
            cursor: pointer;
        }

        .button:hover {
            background: var(--bb-green-dark);
        }

        .button:focus-visible {
            outline: 2px solid var(--bb-gold);
            outline-offset: 2px;
        }

        .button--link {
            display: inline-block;
            color: #fff;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <main class="shell">
        <header class="shell__head">
            <p class="brand">Baobab</p>
            <p class="brand__sub">C'est installé</p>
        </header>

        @include('baobab::install.partials.final')
    </main>
</body>
</html>
