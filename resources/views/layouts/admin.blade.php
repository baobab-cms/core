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
<body class="flex h-full flex-col bg-surface text-foreground antialiased" x-data="{ sidebarOpen: false, sidebarCollapsed: (localStorage.getItem('baobab.sidebar.collapsed') ?? 'false') === 'true' }">
    @include('baobab::layouts.partials.impersonation-banner')

    @include('baobab::layouts.partials.staging-noindex-banner')

    {{--
        Après les bandeaux, et non par-dessus : un toast en `fixed` masquait
        l'avertissement d'environnement non-production, or un bandeau qui
        prévient ne doit jamais être caché par un message passager
        (demandé le 24 août 2026, suivi n° 205).

        En flux normal plutôt qu'en `fixed`, ce que la structure autorise :
        `<body>` est une colonne de hauteur fixe dont seul `<main>` défile, si
        bien que tout ce qui se trouve ici reste visible sans avoir à sortir du
        flux. Contrepartie assumée : l'apparition d'un toast décale le contenu
        vers le bas de sa hauteur. C'est le prix d'un message qui ne recouvre
        jamais rien.
    --}}
    <x-baobab::toasts />

    @include('baobab::layouts.partials.admin-topbar')

    <div class="flex flex-1 overflow-hidden">
        @include('baobab::layouts.partials.admin-sidebar')

        <div class="flex flex-1 flex-col overflow-hidden">
            @hasSection('page-title')
                <header class="flex items-center justify-between border-b border-border px-6 py-4">
                    <h1 class="text-lg font-semibold text-foreground">@yield('page-title')</h1>
                    <div>@yield('page-actions')</div>
                </header>
            @endif

            @stack('admin.content.before')

            <main class="flex-1 overflow-y-auto px-6 py-6">
                @yield('content')
            </main>
        </div>
    </div>

    @include('baobab::layouts.partials.admin-footer')

    @include('baobab::layouts.partials.admin-omnibox')

    @stack('admin.scripts')
</body>
</html>
