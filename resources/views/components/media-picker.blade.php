@props([
    'name',
    'type' => null,
    'multiple' => false,
    'indexUrl',
    'storeUrl',
    'chunkUrl',
    'csrfToken',
])

{{--
    Modale de sélection de média (spec 06 §6 — « un seul composant partout ») : navigation avec
    recherche, upload inline. Sélection unique par défaut (`image`/`file`) ; `multiple` active la
    sélection ordonnée utilisée par le champ `gallery` — l'ordre y est celui du clic, ajusté ensuite
    par le champ appelant (boutons ↑/↓). Émet `media-selected` (bulle normalement dans le DOM, pas
    via .window) — l'appelant doit imbriquer ce composant dans son propre wrapper x-data pour
    l'écouter sans collision si plusieurs champs médias existent sur le même formulaire.
--}}
<x-baobab::modal :name="$name" max-width="xl" :aria-label="__('baobab::admin.media.title')">
    <div
        x-data="mediaPicker({
            indexUrl: @js($indexUrl),
            storeUrl: @js($storeUrl),
            chunkUrl: @js($chunkUrl),
            csrfToken: @js($csrfToken),
            type: @js($type),
            multiple: @js((bool) $multiple),
        })"
        x-init="load()"
    >
        <div class="mb-3 flex items-center gap-2">
            <input
                type="text"
                x-model="search"
                x-on:input.debounce.400ms="load(1)"
                placeholder="{{ __('baobab::admin.media.search_placeholder') }}"
                class="flex-1 rounded-md border border-border bg-surface px-3 py-2 text-sm text-foreground"
            >
            <button
                type="button"
                x-on:click="$refs.pickerFileInput.click()"
                class="rounded-md border border-border bg-surface px-3 py-2 text-xs font-medium text-foreground hover:bg-surface-subtle"
            >
                {{ __('baobab::admin.media.upload_action') }}
            </button>
            <input type="file" x-ref="pickerFileInput" class="hidden" x-on:change="onFileInput($event)" @if ($type === 'image') accept="image/*" @endif>
        </div>

        <div
            class="grid max-h-96 grid-cols-3 gap-2 overflow-y-auto rounded-md border border-border p-2 sm:grid-cols-4 md:grid-cols-5"
            x-bind:class="{ 'border-primary bg-surface-subtle': dragging }"
            x-on:dragover.prevent="dragging = true"
            x-on:dragleave.prevent="dragging = false"
            x-on:drop.prevent="dragging = false; onDrop($event)"
        >
            <template x-if="!loading && items.length === 0">
                <p class="col-span-full py-6 text-center text-sm text-muted">{{ __('baobab::admin.components.no_results') }}</p>
            </template>

            <template x-for="item in items" :key="item.id">
                <button
                    type="button"
                    x-on:click="select(item)"
                    x-bind:class="{ 'ring-2 ring-primary': isSelected(item.id) }"
                    class="overflow-hidden rounded-md border border-border bg-surface text-left"
                >
                    <template x-if="(item.mime_type && item.mime_type.startsWith('image/')) || item.source === 'external'">
                        <img :src="item.url" :alt="item.alt || item.file_name" class="h-20 w-full object-cover">
                    </template>
                    <template x-if="!((item.mime_type && item.mime_type.startsWith('image/')) || item.source === 'external')">
                        <div class="flex h-20 w-full items-center justify-center bg-surface-subtle text-xs text-muted" x-text="item.file_name"></div>
                    </template>
                </button>
            </template>
        </div>

        <div class="mt-3 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <button type="button" x-show="page > 1" x-on:click="load(page - 1)" class="rounded-md border border-border px-2 py-1 text-xs hover:bg-surface-subtle">&larr;</button>
                <button type="button" x-show="page < lastPage" x-on:click="load(page + 1)" class="rounded-md border border-border px-2 py-1 text-xs hover:bg-surface-subtle">&rarr;</button>
            </div>

            <div class="flex gap-2">
                <x-baobab::button type="button" variant="secondary" x-on:click="show = false">
                    {{ __('baobab::admin.components.close') }}
                </x-baobab::button>
                <x-baobab::button type="button" variant="primary" x-bind:disabled="!hasSelection()" x-on:click="confirmSelection(); show = false">
                    {{ __('baobab::admin.media.picker_choose_action') }}
                </x-baobab::button>
            </div>
        </div>
    </div>
</x-baobab::modal>

@once
    <script>
        function mediaPicker(config) {
            return {
                items: [],
                page: 1,
                lastPage: 1,
                loading: false,
                dragging: false,
                search: '',
                selectedId: null,
                selectedItem: null,
                selectedIds: [],
                selectedItems: [],

                async load(page = 1) {
                    this.loading = true;

                    try {
                        const url = new URL(config.indexUrl, window.location.origin);
                        url.searchParams.set('page', page);
                        if (this.search) url.searchParams.set('q', this.search);
                        if (config.type) url.searchParams.set('type', config.type);

                        const response = await fetch(url, { headers: { Accept: 'application/json' } });
                        const body = await response.json();

                        this.items = body.data;
                        this.page = body.current_page;
                        this.lastPage = body.last_page;
                    } finally {
                        this.loading = false;
                    }
                },

                select(item) {
                    if (!config.multiple) {
                        this.selectedId = item.id;
                        this.selectedItem = item;
                        return;
                    }

                    const index = this.selectedIds.indexOf(item.id);

                    if (index === -1) {
                        this.selectedIds.push(item.id);
                        this.selectedItems.push(item);
                    } else {
                        this.selectedIds.splice(index, 1);
                        this.selectedItems.splice(index, 1);
                    }
                },

                isSelected(id) {
                    return config.multiple ? this.selectedIds.includes(id) : this.selectedId === id;
                },

                hasSelection() {
                    return config.multiple ? this.selectedItems.length > 0 : !!this.selectedId;
                },

                confirmSelection() {
                    if (!this.hasSelection()) {
                        return;
                    }

                    this.$dispatch('media-selected', config.multiple ? this.selectedItems : this.selectedItem);

                    if (config.multiple) {
                        this.selectedIds = [];
                        this.selectedItems = [];
                    }
                },

                onFileInput(event) {
                    const [file] = event.target.files;
                    event.target.value = '';
                    if (file) this.upload(file);
                },

                onDrop(event) {
                    const [file] = event.dataTransfer.files;
                    if (file) this.upload(file);
                },

                async upload(file) {
                    const body = new FormData();
                    body.append('file', file);

                    const response = await fetch(config.storeUrl, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': config.csrfToken, Accept: 'application/json' },
                        body,
                    });

                    if (!response.ok) {
                        return;
                    }

                    const uploaded = await response.json();

                    // La réponse d'upload est le modèle Media brut (pas de `url`, méthode non
                    // sérialisée) — on recharge la première page (projection JSON du picker,
                    // qui a bien `url`) et on sélectionne l'élément qu'on vient de créer dedans.
                    await this.load(1);

                    const found = this.items.find((item) => item.id === uploaded.id);
                    if (found) {
                        this.select(found);
                    }
                },
            };
        }
    </script>
@endonce
