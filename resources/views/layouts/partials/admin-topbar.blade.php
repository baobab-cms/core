<header class="flex h-16 items-center justify-between border-b border-border bg-surface px-4">
    <div class="flex items-center gap-3">
        <button
            type="button"
            class="lg:hidden"
            @click="sidebarOpen = !sidebarOpen"
            aria-label="{{ __('baobab::admin.layout.toggle_sidebar') }}"
        >
            <span aria-hidden="true">&#9776;</span>
        </button>

        <a href="{{ route('admin.dashboard') }}" class="font-semibold text-foreground">
            Baobab
        </a>
    </div>

    <div class="flex-1"></div>

    @stack('admin.topbar.before-user')

    @auth('baobab')
        <div class="relative" x-data="{ userMenuOpen: false }">
            <button
                type="button"
                @click="userMenuOpen = !userMenuOpen"
                class="flex items-center gap-2 text-sm text-foreground"
            >
                {{ auth('baobab')->user()?->name }}
            </button>

            <div
                x-show="userMenuOpen"
                @click.outside="userMenuOpen = false"
                x-cloak
                class="absolute right-0 top-10 z-10 rounded-md border border-border bg-surface p-2 shadow-lg"
            >
                <a
                    href="{{ route('admin.account.security.show') }}"
                    class="block whitespace-nowrap rounded-md px-2 py-1 text-left text-sm text-foreground hover:bg-surface-subtle"
                >
                    {{ __('baobab::admin.account.security.title') }}
                </a>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="w-full whitespace-nowrap rounded-md px-2 py-1 text-left text-sm text-foreground hover:bg-surface-subtle">
                        {{ __('baobab::admin.layout.logout') }}
                    </button>
                </form>
            </div>
        </div>
    @endauth
</header>
