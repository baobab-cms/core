{{--
    Layout autonome du wizard — spec 15 §6.1, Pass C1.

    **Aucun `@vite` ici, et c'est la décision structurante de la passe**
    (suivi n° 223). L'admin et le layout invité tirent leur CSS de
    l'application hôte ; l'installateur ne le peut pas, puisqu'il tourne
    précisément quand rien ne garantit qu'un build existe. Il sert donc ses
    propres fichiers depuis `public/baobab/install/`.

    **Et non `public/install/`, que le §6.3 nommait** : un répertoire à cet
    emplacement éclipse la route `/install`, le `.htaccess` de Laravel excluant
    du contrôleur frontal toute requête qui correspond à un répertoire existant.
    Trouvé en recette le 26 août 2026 ; le §6.3 a été amendé en conséquence.

    **Pas de `<x-baobab::design-tokens />` non plus** : ce composant résout les
    tokens depuis la base, qui n'existe pas encore à cette étape. Les valeurs de
    marque sont donc figées dans la feuille de style du wizard — écart assumé et
    consigné, pas un oubli.

    L'attribut `?v=` porte la version du CMS : une archive livre des fichiers
    au même nom d'une version à l'autre, et un navigateur qui garde l'ancienne
    feuille afficherait un wizard cassé sans que personne comprenne pourquoi.
--}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Installation') — Baobab</title>

    <link rel="stylesheet" href="{{ url('baobab/install/wizard.css') }}?v={{ config('baobab.version', 'dev') }}">
</head>
<body>
    <main class="shell">
        <header class="shell__head">
            <p class="brand">Baobab</p>
            <p class="brand__sub">@yield('subtitle', 'Installation')</p>
        </header>

        @yield('content')
    </main>
</body>
</html>
