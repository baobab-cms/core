<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title')</title>

    <x-baobab::design-tokens />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex h-full items-center justify-center bg-surface-subtle">
    <div class="w-full max-w-sm rounded-lg border border-border bg-surface p-8 shadow-sm">
        <h1 class="mb-6 text-center text-lg font-semibold text-foreground">Baobab</h1>

        @yield('content')
    </div>

    <x-baobab::toasts />
</body>
</html>
