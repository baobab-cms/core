{{-- Recouvrement mobile : ferme le tiroir au clic en dehors --}}
<div
    x-show="sidebarOpen"
    x-cloak
    class="fixed inset-0 z-20 bg-black/50 lg:hidden"
    @click="sidebarOpen = false"
></div>

<aside
    class="fixed inset-y-16 left-0 z-30 w-64 -translate-x-full transform overflow-y-auto border-r border-border bg-surface-subtle transition-transform duration-200 ease-in-out lg:static lg:inset-auto lg:z-auto lg:w-64 lg:shrink-0 lg:translate-x-0"
    :class="{ 'translate-x-0': sidebarOpen }"
>
    <nav class="flex h-full flex-col justify-between p-4">
        <ul class="space-y-1">
            @include('baobab::layouts.partials.admin-sidebar-items', ['items' => $sidebar ?? []])
        </ul>

        <div class="mt-4 border-t border-border pt-4">
            @stack('admin.sidebar.footer')
        </div>
    </nav>
</aside>
