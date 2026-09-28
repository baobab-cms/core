{{-- Recouvrement mobile : ferme le tiroir au clic en dehors --}}
<div
    x-show="sidebarOpen"
    x-cloak
    class="fixed inset-0 z-20 bg-black/50 lg:hidden"
    @click="sidebarOpen = false"
></div>

<aside
    class="group fixed inset-y-0 left-0 z-30 flex w-64 -translate-x-full transform flex-col border-r border-border bg-surface-subtle transition-transform duration-200 ease-in-out lg:translate-x-0 lg:transition-[width] lg:duration-200 lg:data-[collapsed]:w-20"
    :class="{ 'translate-x-0': sidebarOpen }"
    :data-collapsed="sidebarCollapsed ? '' : null"
>
    {{--
        Ligne de logo, même hauteur (h-16) et même bordure basse que la
        topbar : les deux forment une seule rangée visuelle continue plutôt
        que deux blocs empilés (direction-visuelle.md §7.1, revu après
        premier rendu navigateur — M9 point 5, Pass A, suivi n° 366).

        Icône + nom de marque côte à côte, jamais l'un ou l'autre : ce que
        l'utilisateur a demandé en revoyant le premier rendu, à structure
        égale avec la référence fournie, palette claire inchangée (§2.3,
        aucun mode sombre avant v1). L'icône réutilise le favicon de la
        marque, déjà carré et déjà pensé pour un petit format — pas de
        nouveau champ ; le repli est l'initiale du nom d'application, jamais
        un fichier absent. Seul le nom se cache en sidebar repliée, l'icône
        reste seule (§3.5).
    --}}
    <a
        href="{{ route('admin.dashboard') }}"
        class="flex h-16 shrink-0 items-center gap-2.5 border-b border-border px-4 group-data-[collapsed]:justify-center group-data-[collapsed]:px-0"
    >
        @if ($branding->favicon)
            <img
                src="{{ $branding->favicon->url() }}"
                alt=""
                class="h-8 w-8 shrink-0 rounded-md"
            >
        @else
            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-primary font-display text-sm font-semibold text-white">
                {{ mb_substr(config('app.name', 'Baobab'), 0, 1) }}
            </span>
        @endif

        <span class="truncate font-display text-lg font-semibold leading-none text-foreground group-data-[collapsed]:hidden">
            {{ config('app.name', 'Baobab') }}
        </span>
    </a>

    <nav class="flex flex-1 flex-col justify-between overflow-y-auto p-4" aria-label="{{ __('baobab::admin.sidebar.nav_label') }}">
        <div>
            <button
                type="button"
                class="mb-2 hidden w-full items-center justify-center rounded-md px-3 py-2 text-sm text-foreground hover:bg-surface lg:flex"
                @click="sidebarCollapsed = !sidebarCollapsed; localStorage.setItem('baobab.sidebar.collapsed', sidebarCollapsed)"
                :aria-pressed="sidebarCollapsed"
                aria-label="{{ __('baobab::admin.sidebar.toggle_collapse') }}"
            >
                <x-baobab::icon name="bi-chevron-double-left" class="h-4 w-4 shrink-0 group-data-[collapsed]:rotate-180" />
            </button>

            <ul class="space-y-1">
                @include('baobab::layouts.partials.admin-sidebar-items', ['items' => $sidebar ?? []])
            </ul>
        </div>

        <div class="mt-4 border-t border-border pt-4">
            @stack('admin.sidebar.footer')
        </div>
    </nav>
</aside>
