@auth('baobab')
    {{-- Omnibox admin (spec 11 §4.1) — Ctrl/Cmd+K partout dans l'admin, une
         seule boîte interrogeant toutes les sources de contexte « admin »
         (endpoint admin.omnibox.search → RunSearch), résultats groupés par
         source, navigation clavier. Patron Alpine « notificationCenter »
         (admin-topbar) : config injectée, script @once. --}}
    <div
        x-data="omnibox({ searchUrl: '{{ route('admin.omnibox.search') }}' })"
        x-on:keydown.window.prevent.ctrl.k="show()"
        x-on:keydown.window.prevent.cmd.k="show()"
        x-on:open-omnibox.window="show()"
        x-on:keydown.escape.window="open = false"
        x-show="open"
        x-cloak
        class="fixed inset-0 z-50 flex items-start justify-center bg-black/50 pt-24"
        x-on:click.self="open = false"
    >
        <div class="w-full max-w-xl rounded-lg border border-border bg-surface shadow-xl">
            <input
                type="search"
                x-ref="input"
                x-model="query"
                x-on:input.debounce.300ms="search()"
                x-on:keydown.down.prevent="move(1)"
                x-on:keydown.up.prevent="move(-1)"
                x-on:keydown.enter.prevent="go()"
                placeholder="{{ __('baobab::admin.omnibox.placeholder') }}"
                class="w-full rounded-t-lg border-b border-border bg-surface px-4 py-3 text-sm text-foreground focus:outline-none"
            >

            <div class="max-h-96 overflow-y-auto p-2" x-show="groups.length > 0">
                <template x-for="group in groups" :key="group.source">
                    <div class="mb-2">
                        <p class="px-2 py-1 text-xs font-medium uppercase text-muted" x-text="group.label"></p>

                        <template x-for="item in group.items" :key="item.url">
                            <a
                                :href="item.url"
                                class="block rounded-md px-2 py-2 text-sm text-foreground hover:bg-surface-subtle"
                                :class="{ 'bg-surface-subtle': isActive(item) }"
                            >
                                <span class="font-medium" x-text="item.title"></span>
                                <span class="ml-2 text-xs text-muted" x-text="item.excerpt"></span>
                            </a>
                        </template>
                    </div>
                </template>
            </div>

            <p
                class="px-4 py-3 text-sm text-muted"
                x-show="query.length >= 2 && groups.length === 0 && ! loading"
            >{{ __('baobab::admin.omnibox.empty') }}</p>
        </div>
    </div>

    @once
        <script>
            function omnibox(config) {
                return {
                    open: false,
                    loading: false,
                    query: '',
                    groups: [],
                    activeIndex: -1,

                    show() {
                        this.open = true;
                        this.$nextTick(() => this.$refs.input.focus());
                    },

                    search() {
                        if (this.query.trim().length < 2) {
                            this.groups = [];
                            this.activeIndex = -1;

                            return;
                        }

                        this.loading = true;

                        fetch(config.searchUrl + '?q=' + encodeURIComponent(this.query), { headers: { Accept: 'application/json' } })
                            .then((response) => response.json())
                            .then((data) => {
                                this.groups = data;
                                this.activeIndex = data.length > 0 ? 0 : -1;
                            })
                            .finally(() => { this.loading = false; });
                    },

                    flat() {
                        return this.groups.flatMap((group) => group.items);
                    },

                    move(delta) {
                        const total = this.flat().length;

                        if (total === 0) {
                            return;
                        }

                        this.activeIndex = (this.activeIndex + delta + total) % total;
                    },

                    isActive(item) {
                        return this.flat()[this.activeIndex] === item;
                    },

                    go() {
                        const item = this.flat()[this.activeIndex];

                        if (item) {
                            window.location.href = item.url;
                        }
                    },
                };
            }
        </script>
    @endonce
@endauth
