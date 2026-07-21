<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', __('baobab::admin.layout.default_title'))</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @if ($branding->favicon)
        <link rel="icon" href="{{ $branding->favicon->url() }}">
    @endif

    @if ($branding->primary_color)
        <style>:root { --color-primary: {{ $branding->primary_color }}; }</style>
    @endif

    @stack('admin.head')
</head>
<body class="flex h-full flex-col bg-surface text-foreground antialiased" x-data="{ sidebarOpen: false }">
    @include('baobab::layouts.partials.impersonation-banner')

    @include('baobab::layouts.partials.staging-noindex-banner')

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

    <x-baobab::toasts />

    @stack('admin.scripts')
</body>
</html>
