<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', __('baobab::admin.layout.default_title'))</title>

    <x-baobab::design-tokens />

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @if ($branding->favicon)
        <link rel="icon" href="{{ $branding->favicon->url() }}">
    @endif

    @stack('admin.head')
</head>
<body class="h-full bg-surface text-foreground antialiased" x-data="{ sidebarOpen: false, sidebarCollapsed: (localStorage.getItem('baobab.sidebar.collapsed') ?? 'false') === 'true' }">
    {{--
        La sidebar est fixe et pleine hauteur, coin supérieur gauche compris
        (direction-visuelle.md §7.1) : sa ligne de logo (h-16) s'aligne avec
        la topbar sur une seule et même rangée visuelle, plutôt que deux
        rangées empilées comme avant (M9 point 5, Pass A, revu après premier
        rendu navigateur — position du logo jugée non optimale, suivi
        n° 366). La colonne de droite lui laisse la place par un remplissage
        gauche (`lg:pl-64`/`lg:pl-20`), pas par un flex sibling.
    --}}
    @include('baobab::layouts.partials.admin-sidebar')

    <div
        class="flex h-full flex-col transition-[padding] duration-200"
        :class="sidebarCollapsed ? 'lg:pl-20' : 'lg:pl-64'"
    >
        @include('baobab::layouts.partials.impersonation-banner')

        @include('baobab::layouts.partials.staging-noindex-banner')

        {{--
            Après les bandeaux, et non par-dessus : un toast en `fixed` masquait
            l'avertissement d'environnement non-production, or un bandeau qui
            prévient ne doit jamais être caché par un message passager
            (demandé le 24 août 2026, suivi n° 205).

            En flux normal plutôt qu'en `fixed`, ce que la structure autorise :
            cette colonne est de hauteur fixe et seul `<main>` défile, si bien
            que tout ce qui se trouve ici reste visible sans avoir à sortir du
            flux. Contrepartie assumée : l'apparition d'un toast décale le
            contenu vers le bas de sa hauteur. C'est le prix d'un message qui
            ne recouvre jamais rien.
        --}}
        <x-baobab::toasts />

        @include('baobab::layouts.partials.admin-topbar')

        <div class="flex flex-1 flex-col overflow-hidden">
            @stack('admin.content.before')

            <main class="flex-1 overflow-y-auto px-6 py-6">
                @yield('content')
            </main>
        </div>

        @include('baobab::layouts.partials.admin-footer')
    </div>

    @include('baobab::layouts.partials.admin-omnibox')

    @stack('admin.scripts')
</body>
</html>
