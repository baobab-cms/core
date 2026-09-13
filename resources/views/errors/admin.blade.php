{{--
    Filet d'exception admin (suivi n° 202).

    Même contrainte que `errors/500.blade.php` : rien de ce que cette page
    affiche ne dépend d'un artefact compilé, d'une requête en base ou d'un
    composant — une page d'erreur qui a besoin que le reste fonctionne
    échoue précisément au moment où elle est censée aider. CSS en dur,
    inline, en valeurs littérales (pas de custom properties `--bb-*` : cette
    chaîne sert ailleurs de marqueur des tokens compilés, à ne pas laisser
    croire que cette page en dépend). Le lien vers le tableau de bord est une
    génération d'URL depuis les routes déjà chargées, jamais une requête.

    Couleurs et police : même arbitrage que `resources/install/wizard.css`
    (spec 15 §6.1, suivi n° 223), pour la même raison — une page qui doit
    survivre à une base de données ou un build indisponibles ne peut pas
    aller chercher le thème actif ou le profil de marque (c'est précisément
    ce qui a fait tomber l'écran de connexion au suivi n° 242). Couleurs de
    marque **figées en dur** (`#1E7A54` / `#E9A13B`, celles de l'admin, pas
    une déclinaison dynamique), pile système plutôt que Bricolage/Figtree —
    aucune police distante, aucune dépendance au build (décision du 13
    septembre 2026).
--}}
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <style>
        body {
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: #fdfcfa;
            color: #24211d;
            font-family: system-ui, -apple-system, "Segoe UI", sans-serif;
            line-height: 1.6;
        }
        main { max-width: 34rem; padding: 2rem; }
        h1 { font-size: 1.5rem; font-weight: 600; margin: 0 0 .75rem; }
        p { margin: 0 0 1.5rem; color: #6b655c; }
        .button {
            display: inline-block;
            padding: 0.625rem 1rem;
            background: #1E7A54;
            color: #fff;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
        }
        .button:hover { background: #17603F; }
        .button:focus-visible { outline: 2px solid #E9A13B; outline-offset: 2px; }
    </style>
</head>
<body>
    <main>
        <h1>{{ $title }}</h1>
        <p>{{ $body }}</p>
        <a class="button" href="{{ route('admin.dashboard') }}">{{ $backToDashboard }}</a>
    </main>
</body>
</html>
