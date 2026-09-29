<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title')</title>

    <x-baobab::design-tokens />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
{{--
    Layout dédié à `login` (M9 point 5, Pass D, suivi n° 373 décision 5) : le
    seul écran à afficher la marque Baobab au-dessus de la carte plutôt que le
    mot « Baobab » dedans (décision 4/8) — les 6 autres écrans d'authentification
    restent sur `layouts/guest.blade.php`, inchangé.

    Même patron colonne que `guest.blade.php` (n° 205/227 : les toasts avant la
    carte, jamais de `gap` sur le conteneur — un conteneur de toasts vide
    prendrait quand même l'espace du gap et décalerait le reste). `px-4` pour
    que la carte n'affleure jamais les bords sur un mobile étroit (~360 px).

    `min-h-svh`/`justify-center`, réservés à `sm:` et plus, même correctif que
    `guest.blade.php` (voir son commentaire pour le détail complet, deux allers-
    retours avec l'utilisateur) : centrer un formulaire court dans toute la
    hauteur d'un mobile haut ne fait que le repousser sous le pli. En dessous
    de `sm:`, `body` suit son flux naturel — `py-8` donne l'espace en haut et
    en bas, rien à recalculer.
--}}
<body class="flex flex-col items-center bg-surface-subtle px-4 py-8 sm:min-h-svh sm:justify-center">
    <x-baobab::toasts />

    <div class="mb-6">
        <x-baobab::brand-mark class="h-14 w-14 rounded-lg" />
    </div>

    <div class="w-full max-w-sm rounded-lg border border-border bg-surface p-8 shadow-sm">
        @yield('content')
    </div>
</body>
</html>
