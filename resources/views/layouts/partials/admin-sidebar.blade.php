{{-- Recouvrement mobile : ferme le tiroir au clic en dehors --}}
<div
    x-show="sidebarOpen"
    x-cloak
    class="fixed inset-0 z-20 bg-black/50 lg:hidden"
    @click="sidebarOpen = false"
></div>

<aside
    class="group fixed inset-y-16 left-0 z-30 w-64 -translate-x-full transform overflow-y-auto border-r border-border bg-surface-subtle transition-transform duration-200 ease-in-out lg:static lg:inset-auto lg:z-auto lg:w-64 lg:shrink-0 lg:translate-x-0 lg:transition-[width] lg:duration-200 lg:data-[collapsed]:w-20"
    :class="{ 'translate-x-0': sidebarOpen }"
    :data-collapsed="sidebarCollapsed ? '' : null"
>
    <nav class="flex h-full flex-col justify-between p-4">
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
