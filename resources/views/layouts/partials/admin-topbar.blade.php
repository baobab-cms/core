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
            @if ($branding->logo)
                <img src="{{ $branding->logo->url() }}" alt="{{ config('app.name', 'Baobab') }}" class="h-8 w-auto">
            @else
                Baobab
            @endif
        </a>
    </div>

    <div class="flex-1"></div>

    @auth('baobab')
        <button
            type="button"
            class="mr-3 flex items-center gap-2 rounded-md border border-border bg-surface px-3 py-1.5 text-sm text-muted hover:bg-surface-subtle"
            x-on:click="window.dispatchEvent(new CustomEvent('open-omnibox'))"
        >
            <span aria-hidden="true">&#128269;</span>
            <span class="hidden sm:inline">{{ __('baobab::admin.omnibox.trigger_label') }}</span>
            <kbd class="hidden rounded border border-border px-1 text-xs sm:inline">Ctrl+K</kbd>
        </button>
    @endauth

    @stack('admin.topbar.before-user')

    @auth('baobab')
        <div class="flex items-center gap-3">
            <div
                class="relative"
                x-data="notificationCenter({
                    pollUrl: '{{ route('admin.notifications.poll') }}',
                    readBaseUrl: '{{ url('admin/notifications') }}',
                    readAllUrl: '{{ route('admin.notifications.read-all') }}',
                    csrfToken: '{{ csrf_token() }}',
                    initialCount: {{ $unreadNotificationsCount }},
                })"
                x-init="init()"
            >
                <button
                    type="button"
                    @click="open = !open"
                    class="relative flex items-center text-foreground"
                    aria-label="{{ __('baobab::admin.notifications.title') }}"
                >
                    <span aria-hidden="true">&#128276;</span>
                    <span
                        x-show="count > 0"
                        x-cloak
                        x-text="count"
                        class="absolute -right-2 -top-2 flex h-4 min-w-4 items-center justify-center rounded-full bg-danger px-1 text-[10px] text-white"
                    ></span>
                </button>

                <div
                    x-show="open"
                    @click.outside="open = false"
                    x-cloak
                    class="absolute right-0 top-10 z-10 w-80 rounded-md border border-border bg-surface p-2 shadow-lg"
                >
                    <template x-if="items.length === 0">
                        <p class="px-2 py-2 text-sm text-muted">{{ __('baobab::admin.notifications.empty') }}</p>
                    </template>

                    <template x-for="item in items" :key="item.id">
                        <div class="flex items-start justify-between gap-2 border-b border-border px-2 py-2 text-sm last:border-0">
                            <div>
                                <template x-if="item.url">
                                    <a :href="item.url" x-text="item.description" x-bind:class="item.read ? 'text-muted' : 'text-foreground'" class="hover:underline"></a>
                                </template>
                                <template x-if="!item.url">
                                    <span x-text="item.description" x-bind:class="item.read ? 'text-muted' : 'text-foreground'"></span>
                                </template>
                                <p class="text-xs text-muted" x-text="item.created_at"></p>
                            </div>
                            <button type="button" x-show="!item.read" x-cloak @click="markRead(item.id)" class="whitespace-nowrap text-xs text-primary hover:underline">
                                {{ __('baobab::admin.notifications.mark_read_action') }}
                            </button>
                        </div>
                    </template>

                    <div class="mt-2 flex items-center justify-between border-t border-border pt-2">
                        <button type="button" @click="markAllRead()" class="text-xs text-primary hover:underline">
                            {{ __('baobab::admin.notifications.mark_all_read_action') }}
                        </button>
                        <a href="{{ route('admin.notifications.index') }}" class="text-xs text-primary hover:underline">
                            {{ __('baobab::admin.notifications.view_all_action') }}
                        </a>
                    </div>
                </div>
            </div>

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
                        href="{{ route('admin.account.notifications.show') }}"
                        class="block whitespace-nowrap rounded-md px-2 py-1 text-left text-sm text-foreground hover:bg-surface-subtle"
                    >
                        {{ __('baobab::admin.notifications.preferences_title') }}
                    </a>

                    <a
                        href="{{ route('admin.account.security.show') }}"
                        class="block whitespace-nowrap rounded-md px-2 py-1 text-left text-sm text-foreground hover:bg-surface-subtle"
                    >
                        {{ __('baobab::admin.account.security.title') }}
                    </a>

                    <a
                        href="{{ route('admin.account.api-tokens.index') }}"
                        class="block whitespace-nowrap rounded-md px-2 py-1 text-left text-sm text-foreground hover:bg-surface-subtle"
                    >
                        {{ __('baobab::admin.account.api_tokens.title') }}
                    </a>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="w-full whitespace-nowrap rounded-md px-2 py-1 text-left text-sm text-foreground hover:bg-surface-subtle">
                            {{ __('baobab::admin.layout.logout') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    @endauth
</header>

@once
    <script>
        function notificationCenter(config) {
            return {
                open: false,
                count: config.initialCount,
                items: [],

                init() {
                    this.poll();
                    setInterval(() => this.poll(), 30000);
                },

                poll() {
                    fetch(config.pollUrl, { headers: { Accept: 'application/json' } })
                        .then((response) => response.json())
                        .then((data) => {
                            this.count = data.count;
                            this.items = data.items;
                        });
                },

                markRead(id) {
                    fetch(`${config.readBaseUrl}/${id}/read`, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': config.csrfToken, Accept: 'application/json' },
                    }).then(() => this.poll());
                },

                markAllRead() {
                    fetch(config.readAllUrl, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': config.csrfToken, Accept: 'application/json' },
                    }).then(() => this.poll());
                },
            };
        }
    </script>
@endonce
