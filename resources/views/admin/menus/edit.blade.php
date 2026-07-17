@extends('baobab::layouts.admin')

@section('title', $menu->name)

@section('content')
    <x-baobab::page :title="$menu->name" :breadcrumbs="[[__('baobab::admin.menus.title'), route('admin.menus.index')], $menu->name]">
        <div
            x-data="menuBuilder(@js($itemsTree), '{{ route('admin.menus.search-content') }}')"
            x-init="init()"
        >
            <form method="POST" action="{{ route('admin.menus.update', ['menu' => $menu->id]) }}" x-ref="form" x-on:submit="itemsInput = JSON.stringify(itemsPayload())">
                @csrf

                <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <div class="space-y-4 lg:col-span-2">
                        <x-baobab::card :header="__('baobab::admin.menus.items_header')">
                            <template x-if="items.length === 0">
                                <p class="text-sm text-muted">{{ __('baobab::admin.menus.no_items') }}</p>
                            </template>

                            <ul class="divide-y divide-border">
                                <template x-for="(item, index) in items" :key="item._key">
                                    <li class="py-2" :style="{ paddingLeft: (item.depth * 1.5) + 'rem' }">
                                        <div class="flex items-center justify-between gap-2">
                                            <div class="min-w-0 flex-1">
                                                <p class="truncate text-sm font-medium text-foreground" x-text="item.label || '('+item.type+')'"></p>
                                                <p class="truncate text-xs text-muted" x-text="item.url || item.content_type_key || ''"></p>
                                            </div>

                                            <div class="flex items-center gap-1 text-xs">
                                                <button type="button" x-on:click="indent(index)" class="rounded px-1 hover:bg-surface-subtle" title="{{ __('baobab::admin.menus.indent_action') }}">&rarr;</button>
                                                <button type="button" x-on:click="outdent(index)" class="rounded px-1 hover:bg-surface-subtle" title="{{ __('baobab::admin.menus.outdent_action') }}">&larr;</button>
                                                <button type="button" x-on:click="moveUp(index)" class="rounded px-1 hover:bg-surface-subtle" title="{{ __('baobab::admin.menus.move_up_action') }}">&uarr;</button>
                                                <button type="button" x-on:click="moveDown(index)" class="rounded px-1 hover:bg-surface-subtle" title="{{ __('baobab::admin.menus.move_down_action') }}">&darr;</button>
                                                <button type="button" x-on:click="item.open = !item.open" class="rounded px-1 hover:bg-surface-subtle" title="{{ __('baobab::admin.menus.edit_item_action') }}">&#9881;</button>
                                                <button type="button" x-on:click="removeItem(index)" class="rounded px-1 text-danger hover:bg-surface-subtle" title="{{ __('baobab::admin.menus.remove_item_action') }}">&#10005;</button>
                                            </div>
                                        </div>

                                        <div x-show="item.open" class="mt-2 grid grid-cols-2 gap-2 rounded-md bg-surface-subtle p-2">
                                            <label class="text-xs text-muted">
                                                {{ __('baobab::admin.menus.label_override_label') }}
                                                <input type="text" x-model="item.label" class="mt-1 w-full rounded border border-border px-2 py-1 text-sm">
                                            </label>
                                            <label class="text-xs text-muted">
                                                {{ __('baobab::admin.menus.css_class_label') }}
                                                <input type="text" x-model="item.css_class" class="mt-1 w-full rounded border border-border px-2 py-1 text-sm">
                                            </label>
                                            <label class="text-xs text-muted">
                                                {{ __('baobab::admin.menus.icon_label') }}
                                                <input type="text" x-model="item.icon" class="mt-1 w-full rounded border border-border px-2 py-1 text-sm">
                                            </label>
                                            <label class="text-xs text-muted">
                                                {{ __('baobab::admin.menus.target_label') }}
                                                <select x-model="item.target" class="mt-1 w-full rounded border border-border px-2 py-1 text-sm">
                                                    <option value="_self">_self</option>
                                                    <option value="_blank">_blank</option>
                                                </select>
                                            </label>
                                            <label class="col-span-2 text-xs text-muted">
                                                {{ __('baobab::admin.menus.visibility_label') }}
                                                <select x-model="item.visibility" class="mt-1 w-full rounded border border-border px-2 py-1 text-sm">
                                                    <option value="everyone">{{ __('baobab::admin.menus.visibility_everyone') }}</option>
                                                    <option value="guests">{{ __('baobab::admin.menus.visibility_guests') }}</option>
                                                    <option value="authenticated">{{ __('baobab::admin.menus.visibility_authenticated') }}</option>
                                                </select>
                                            </label>
                                        </div>
                                    </li>
                                </template>
                            </ul>
                        </x-baobab::card>

                        <x-baobab::card :header="__('baobab::admin.menus.add_header')">
                            <div class="space-y-4">
                                <div>
                                    <p class="mb-1 text-xs font-medium text-muted">{{ __('baobab::admin.menus.add_content_label') }}</p>
                                    <input type="text" x-model="searchQuery" x-on:input.debounce.400ms="search()" placeholder="{{ __('baobab::admin.menus.search_placeholder') }}" class="w-full rounded border border-border px-2 py-1 text-sm">
                                    <ul class="mt-1 divide-y divide-border rounded border border-border" x-show="searchResults.length">
                                        <template x-for="result in searchResults" :key="result.linkable_type + result.linkable_id">
                                            <li class="flex items-center justify-between px-2 py-1 text-sm">
                                                <span x-text="result.label"></span>
                                                <button type="button" class="text-primary hover:underline" x-on:click="addContent(result)">{{ __('baobab::admin.menus.add_action') }}</button>
                                            </li>
                                        </template>
                                    </ul>
                                </div>

                                <div>
                                    <p class="mb-1 text-xs font-medium text-muted">{{ __('baobab::admin.menus.add_archive_label') }}</p>
                                    <div class="flex gap-2">
                                        <select x-model="newContentTypeKey" class="w-full rounded border border-border px-2 py-1 text-sm">
                                            <option value="">—</option>
                                            @foreach ($contentTypes as $contentType)
                                                <option value="{{ $contentType->key }}">{{ $contentType->blueprint['label']['plural'] ?? $contentType->key }}</option>
                                            @endforeach
                                        </select>
                                        <x-baobab::button type="button" variant="secondary" x-on:click="addArchive()">{{ __('baobab::admin.menus.add_action') }}</x-baobab::button>
                                    </div>
                                </div>

                                <div>
                                    <p class="mb-1 text-xs font-medium text-muted">{{ __('baobab::admin.menus.add_custom_link_label') }}</p>
                                    <div class="space-y-1">
                                        <input type="text" x-model="newLabel" placeholder="{{ __('baobab::admin.menus.label_placeholder') }}" class="w-full rounded border border-border px-2 py-1 text-sm">
                                        <input type="text" x-model="newUrl" placeholder="https://…" class="w-full rounded border border-border px-2 py-1 text-sm">
                                        <x-baobab::button type="button" variant="secondary" x-on:click="addCustomLink()">{{ __('baobab::admin.menus.add_action') }}</x-baobab::button>
                                    </div>
                                </div>

                                <div>
                                    <p class="mb-1 text-xs font-medium text-muted">{{ __('baobab::admin.menus.add_section_label') }}</p>
                                    <div class="flex gap-2">
                                        <input type="text" x-model="newSectionLabel" placeholder="{{ __('baobab::admin.menus.label_placeholder') }}" class="w-full rounded border border-border px-2 py-1 text-sm">
                                        <x-baobab::button type="button" variant="secondary" x-on:click="addSection()">{{ __('baobab::admin.menus.add_action') }}</x-baobab::button>
                                    </div>
                                </div>
                            </div>
                        </x-baobab::card>
                    </div>

                    <div>
                        <x-baobab::card :header="__('baobab::admin.menus.locations_header')">
                            @forelse ($locations as $location)
                                <label class="mb-2 flex items-center gap-2 text-sm text-foreground">
                                    <input type="checkbox" name="locations[]" value="{{ $location->key }}" @checked($assignedKeys->contains($location->key)) class="rounded border-border">
                                    {{ $location->label }}
                                    @unless ($location->is_active)
                                        <x-baobab::badge variant="warning">{{ __('baobab::admin.menus.orphaned_location') }}</x-baobab::badge>
                                    @endunless
                                </label>
                            @empty
                                <p class="text-sm text-muted">{{ __('baobab::admin.menus.no_locations') }}</p>
                            @endforelse
                        </x-baobab::card>

                        <div class="mt-4">
                            <input type="hidden" name="items" x-bind:value="itemsInput">
                            <x-baobab::button type="submit" variant="primary" class="w-full">
                                {{ __('baobab::admin.menus.save_action') }}
                            </x-baobab::button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </x-baobab::page>
@endsection

@once
    <script>
        function menuBuilder(initialTree, searchUrl) {
            return {
                items: [],
                itemsInput: '[]',
                newLabel: '',
                newSectionLabel: '',
                newUrl: '',
                newContentTypeKey: '',
                searchQuery: '',
                searchResults: [],
                searchUrl,

                init() {
                    this.items = this.flatten(initialTree, 0);
                },

                flatten(nodes, depth) {
                    let result = [];
                    for (const node of nodes) {
                        const { children, ...rest } = node;
                        result.push({ ...rest, depth, open: false, _key: Math.random().toString(36).slice(2) });
                        if (children && children.length) {
                            result = result.concat(this.flatten(children, depth + 1));
                        }
                    }
                    return result;
                },

                addItem(partial) {
                    this.items.push({
                        id: null, depth: 0, type: 'custom_link', linkable_type: null, linkable_id: null,
                        content_type_key: null, url: null, label: null, target: '_self', css_class: null,
                        icon: null, visibility: 'everyone', meta: null, open: true,
                        _key: Math.random().toString(36).slice(2),
                        ...partial,
                    });
                },

                addContent(result) {
                    this.addItem({ type: 'content', linkable_type: result.linkable_type, linkable_id: result.linkable_id, label: result.label });
                    this.searchResults = [];
                    this.searchQuery = '';
                },

                addArchive() {
                    if (!this.newContentTypeKey) return;
                    this.addItem({ type: 'archive', content_type_key: this.newContentTypeKey });
                    this.newContentTypeKey = '';
                },

                addCustomLink() {
                    if (!this.newUrl || !this.newLabel) return;
                    this.addItem({ type: 'custom_link', url: this.newUrl, label: this.newLabel });
                    this.newUrl = '';
                    this.newLabel = '';
                },

                addSection() {
                    if (!this.newSectionLabel) return;
                    this.addItem({ type: 'section', label: this.newSectionLabel });
                    this.newSectionLabel = '';
                },

                search() {
                    if (this.searchQuery.length < 2) {
                        this.searchResults = [];
                        return;
                    }
                    fetch(this.searchUrl + '?q=' + encodeURIComponent(this.searchQuery))
                        .then((response) => response.json())
                        .then((data) => { this.searchResults = data; });
                },

                subtreeBounds(index) {
                    const depth = this.items[index].depth;
                    let end = index + 1;
                    while (end < this.items.length && this.items[end].depth > depth) end++;
                    return [index, end];
                },

                removeItem(index) {
                    const [start, end] = this.subtreeBounds(index);
                    this.items.splice(start, end - start);
                },

                moveUp(index) {
                    const depth = this.items[index].depth;
                    let prev = index - 1;
                    while (prev >= 0 && this.items[prev].depth > depth) prev--;
                    if (prev < 0 || this.items[prev].depth !== depth) return;
                    const [pStart] = this.subtreeBounds(prev);
                    const [start, end] = this.subtreeBounds(index);
                    const block = this.items.splice(start, end - start);
                    this.items.splice(pStart, 0, ...block);
                },

                moveDown(index) {
                    const [start, end] = this.subtreeBounds(index);
                    if (end >= this.items.length || this.items[end].depth !== this.items[index].depth) return;
                    const [, nEnd] = this.subtreeBounds(end);
                    const nextBlock = this.items.splice(end, nEnd - end);
                    this.items.splice(start, 0, ...nextBlock);
                },

                indent(index) {
                    const depth = this.items[index].depth;
                    let prev = index - 1;
                    while (prev >= 0 && this.items[prev].depth > depth) prev--;
                    if (prev < 0 || this.items[prev].depth !== depth) return;
                    const [start, end] = this.subtreeBounds(index);
                    for (let i = start; i < end; i++) this.items[i].depth++;
                },

                outdent(index) {
                    if (this.items[index].depth === 0) return;
                    const [start, end] = this.subtreeBounds(index);
                    for (let i = start; i < end; i++) this.items[i].depth--;
                },

                itemsPayload() {
                    return this.items.map(({ _key, open, ...rest }) => rest);
                },
            };
        }
    </script>
@endonce
