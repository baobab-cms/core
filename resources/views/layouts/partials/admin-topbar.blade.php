{{--
    Ne porte que les quatre fonctions déjà livrées et spécifiées — bascule de
    sidebar, lien tableau de bord, cloche, compte (direction-visuelle.md
    §7.1) — plus le déclencheur de l'omnibox, déjà là. Le logo est parti en
    sidebar avec le lien tableau de bord (M9 point 5, Pass A, suivi n° 366) :
    la topbar ne porte plus jamais de marque, seulement du global.

    Deux zones plutôt qu'un unique espaceur : la recherche à gauche (juste
    après la bascule mobile), les icônes globales groupées à droite — revu
    après rendu navigateur, l'omnibox squeezé contre la cloche ne se lisait
    pas comme sa propre zone.
--}}
<header class="flex h-16 items-center justify-between gap-4 border-b border-border bg-surface px-4">
    <div class="flex flex-1 items-center gap-3">
        <button
            type="button"
            class="lg:hidden"
            @click="sidebarOpen = !sidebarOpen"
            aria-label="{{ __('baobab::admin.layout.toggle_sidebar') }}"
        >
            <span aria-hidden="true">&#9776;</span>
        </button>

        @auth('baobab')
            <button
                type="button"
                class="flex w-full max-w-80 items-center gap-2 rounded-md border border-border bg-surface px-3 py-1.5 text-sm text-muted hover:bg-surface-subtle"
                x-on:click="window.dispatchEvent(new CustomEvent('open-omnibox'))"
            >
                <span aria-hidden="true">&#128269;</span>
                <span class="hidden sm:inline">{{ __('baobab::admin.omnibox.trigger_label') }}</span>
                <kbd class="ml-auto hidden rounded border border-border px-1 text-xs sm:inline">Ctrl+K</kbd>
            </button>
        @endauth
    </div>

    @stack('admin.topbar.before-user')

    @auth('baobab')
        <div class="flex shrink-0 items-center gap-3">
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

            {{--
                Raccourcis vers six écrans déjà dans la sidebar (M9 point 5,
                Pass A, suivi n° 366) — jamais la recherche libre de
                l'omnibox, des destinations fixes. Liste calculée dans
                `registerQuickActionsComposer()`, filtrée aux mêmes
                permissions que la sidebar : un item ici est toujours
                atteignable ailleurs, jamais une fuite d'accès.
            --}}
            @if ($quickActions->isNotEmpty())
                <div class="relative" x-data="{ quickActionsOpen: false }">
                    <button
                        type="button"
                        @click="quickActionsOpen = !quickActionsOpen"
                        class="flex items-center text-foreground"
                        aria-label="{{ __('baobab::admin.quick_actions.title') }}"
                    >
                        <x-baobab::icon name="bi-grid-3x3-gap" class="h-4 w-4" />
                    </button>

                    <div
                        x-show="quickActionsOpen"
                        @click.outside="quickActionsOpen = false"
                        x-cloak
                        class="absolute right-0 top-10 z-10 w-64 rounded-md border border-border bg-surface p-2 shadow-lg"
                    >
                        <p class="px-2 py-1 text-xs font-medium text-muted">{{ __('baobab::admin.quick_actions.title') }}</p>

                        <div class="grid grid-cols-3 gap-1">
                            @foreach ($quickActions as $action)
                                <a
                                    href="{{ $action['url'] }}"
                                    class="flex flex-col items-center gap-1.5 rounded-md p-2 text-center hover:bg-surface-subtle"
                                >
                                    {{-- Palette neutre déjà établie par <x-baobab::badge variant="neutral"> --}}
                                    <span class="flex h-9 w-9 items-center justify-center rounded-md bg-sand-100 text-sand-600">
                                        <x-baobab::icon :name="$action['icon']" class="h-4 w-4" />
                                    </span>
                                    <span class="text-xs text-foreground">{{ $action['label'] }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

            <div class="relative" x-data="{ userMenuOpen: false }">
                <button
                    type="button"
                    @click="userMenuOpen = !userMenuOpen"
                    class="flex items-center gap-2 text-sm text-foreground"
                >
                    <x-baobab::avatar :name="auth('baobab')->user()?->name ?? ''" class="h-8 w-8 text-xs" />
                    <span class="hidden sm:inline">{{ auth('baobab')->user()?->name }}</span>
                </button>

                <div
                    x-show="userMenuOpen"
                    @click.outside="userMenuOpen = false"
                    x-cloak
                    class="absolute right-0 top-10 z-10 w-64 rounded-md border border-border bg-surface p-2 shadow-lg"
                >
                    {{--
                        Carte profil (M9 point 5, Pass A, suivi n° 366) :
                        espace photo réservé, avatar en initiales tant que
                        `User` ne porte aucun champ media pour ça — l'upload
                        est une brique à part, hors retrofit de coquille.
                    --}}
                    <div class="flex items-center gap-3 p-2">
                        <x-baobab::avatar :name="auth('baobab')->user()?->name ?? ''" class="h-10 w-10 text-sm" />

                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-foreground">{{ auth('baobab')->user()?->name }}</p>
                            <p class="truncate text-xs text-muted">{{ auth('baobab')->user()?->email }}</p>
                        </div>
                    </div>

                    <x-baobab::button
                        :href="route('admin.account.profile.show')"
                        size="sm"
                        class="mb-2 w-full justify-center"
                    >
                        {{ __('baobab::admin.account.view_profile_action') }}
                    </x-baobab::button>

                    <div class="border-t border-border pt-1">
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
                    </div>

                    <div class="mt-1 border-t border-border pt-1">
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="w-full whitespace-nowrap rounded-md px-2 py-1 text-left text-sm text-foreground hover:bg-surface-subtle">
                                {{ __('baobab::admin.layout.logout') }}
                            </button>
                        </form>
                    </div>
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
