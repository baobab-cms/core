<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title')</title>

    <x-baobab::design-tokens />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
{{--
    **Une colonne, et non une rangée** — c'est la carte *et* les toasts qui
    vivent ici, pas la carte seule.

    Ce corps était `flex … items-center justify-center` sans direction, donc en
    rangée, du temps où `<x-baobab::toasts />` était `fixed` et n'occupait
    aucune place. Le 24 août 2026, le toast est sorti du flottement pour cesser
    de masquer le bandeau d'environnement (b0b8f2c, suivi n° 205) : il est
    devenu un **second enfant flex**, en `w-full max-w-2xl`. Les deux enfants
    réclamant la largeur, `justify-center` n'avait plus d'espace libre à
    répartir et la carte se collait à gauche, le conteneur de toasts — vide,
    mais bien présent — occupant le reste de la rangée. Signalé le 2 septembre
    2026 sur deux hébergements (suivi n° 227) ; la piste évidente, une purge
    Tailwind à la manière du n° 177, était fausse : le CSS était complet, c'est
    la mise en page qui avait changé sous lui.

    En colonne, `items-center` centre horizontalement et `justify-center`
    verticalement ; le conteneur de toasts, vide, n'occupe aucune hauteur. Les
    toasts passent **avant** la carte pour s'afficher au-dessus d'elle, et le
    composant partagé n'est pas touché — l'administration garde le comportement
    que le n° 205 lui a donné.

    Pas de `gap` entre les deux : il s'appliquerait même conteneur vide et
    décalerait la carte vers le bas. Les marges vivent sur les toasts eux-mêmes.
--}}
<body class="flex h-full flex-col items-center justify-center bg-surface-subtle">
    <x-baobab::toasts />

    <div class="w-full max-w-sm rounded-lg border border-border bg-surface p-8 shadow-sm">
        <h1 class="mb-6 text-center text-lg font-semibold text-foreground">Baobab</h1>

        @yield('content')
    </div>
</body>
</html>
