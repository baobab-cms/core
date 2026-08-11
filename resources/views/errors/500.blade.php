{{--
    Page 500 du Core (spec 19 §6.4).

    **Rien de ce que cette page affiche ne dépend d'un artefact compilé, d'une
    requête en base ou d'un composant.** Ni `<x-baobab::design-tokens />`, ni
    `seo-head`, ni bande admin, ni tokens `--bb-*` : une page d'erreur qui a
    besoin que le reste fonctionne échoue précisément au moment où elle est
    censée aider. Le CSS est donc écrit en dur, inline, et les libellés ont un
    repli littéral si le chargement des traductions lui-même a échoué.

    **Jamais surchargeable par un thème**, contrairement à la page de
    maintenance : une panne serveur se sert sans thème, puisque c'est
    peut-être le thème qui a planté. Elle est rendue par un `renderable()`
    enregistré dans le Core, pas par la hiérarchie de templates ni par le
    namespace `errors` de Laravel — que le thème actif, prépendu à
    `view.paths`, pourrait sinon intercepter.
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
        p { margin: 0; color: #6b655c; }
    </style>
</head>
<body>
    <main>
        <h1>{{ $title }}</h1>
        <p>{{ $body }}</p>
    </main>
</body>
</html>
