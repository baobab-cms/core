{{--
    Page de maintenance du Core (spec 12 §6.1, spec 19 §6.4).

    Contrairement à `errors/500.blade.php`, cette page **consomme
    `<x-baobab::design-tokens />` et les tokens `--bb-*`** (elle n'est pas
    censée survivre à une compilation cassée, contrairement au 500) et **est
    surchargeable par le thème actif** — `resources/views/vendor/baobab/errors/maintenance.blade.php`
    dans un thème, résolu automatiquement par `ThemeViewRegistrar` sans rien
    de plus à câbler. Hors du layout commun des six replis de contenu
    (`layouts/public.blade.php`), au même titre que 500 (spec 19 §6.3) : un
    document autonome, pas une page de site.

    `$retryAfter` (secondes ou null) est fourni par `ToggleMaintenanceMode`.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ __('baobab::rendering.maintenance_title') }}</title>

    <x-baobab::design-tokens />

    <style>
        :root { color-scheme: light; }

        body {
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: var(--bb-color-background, #fff);
            color: var(--bb-color-text, #1a1a1a);
            font-family: var(--bb-font-body, system-ui, sans-serif);
            line-height: var(--bb-leading-relaxed, 1.65);
        }

        main {
            max-width: 34rem;
            padding: var(--bb-spacing-8, 2rem) var(--bb-spacing-4, 1rem);
            text-align: center;
        }

        h1 {
            font-family: var(--bb-font-display, var(--bb-font-body, system-ui, sans-serif));
            font-size: var(--bb-text-3xl, 1.9rem);
            font-weight: var(--bb-weight-semibold, 600);
            margin: 0 0 var(--bb-spacing-4, 1rem);
        }

        p { margin: 0; color: var(--bb-color-muted, #6b6b66); }
        p + p { margin-top: var(--bb-spacing-2, .5rem); }
    </style>
</head>
<body>
    <main>
        <h1>{{ __('baobab::rendering.maintenance_title') }}</h1>
        <p>{{ __('baobab::rendering.maintenance_body') }}</p>

        @if ($retryAfter !== null && is_numeric($retryAfter))
            <p>{{ __('baobab::rendering.maintenance_retry', ['seconds' => $retryAfter]) }}</p>
        @endif
    </main>
</body>
</html>
