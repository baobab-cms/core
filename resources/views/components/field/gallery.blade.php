@props([
    'name',
    'label' => null,
    'items' => [],
])

<div class="mb-4">
    @if ($label)
        <label class="mb-1 block text-sm font-medium text-foreground">{{ $label }}</label>
    @endif

    <div
        x-data="galleryFieldInstance({
            items: @js(collect($items)->map(fn ($media) => [
                'id' => $media->id,
                'url' => $media->url(),
                'file_name' => $media->file_name,
                'mime_type' => $media->mime_type,
                'alt' => $media->alt,
            ])->all()),
        })"
        x-on:media-selected="add($event.detail)"
    >
        <template x-for="item in items" :key="item.id">
            <input type="hidden" name="{{ $name }}[]" x-bind:value="item.id">
        </template>

        <div class="grid grid-cols-3 gap-2 sm:grid-cols-4 md:grid-cols-6">
            <template x-for="(item, index) in items" :key="item.id">
                <div class="overflow-hidden rounded-md border border-border bg-surface">
                    <template x-if="item.mime_type && item.mime_type.startsWith('image/')">
                        <img :src="item.url" :alt="item.alt || item.file_name" class="h-20 w-full object-cover">
                    </template>
                    <template x-if="!item.mime_type || !item.mime_type.startsWith('image/')">
                        <div class="flex h-20 w-full items-center justify-center bg-surface-subtle text-xs text-muted" x-text="item.file_name"></div>
                    </template>

                    <div class="flex items-center justify-between gap-1 border-t border-border p-1">
                        <button type="button" x-bind:disabled="index === 0" x-on:click="moveUp(index)" class="rounded px-1 text-xs hover:bg-surface-subtle disabled:opacity-30" aria-label="{{ __('baobab::admin.media.gallery_move_up') }}">&uarr;</button>
                        <button type="button" x-bind:disabled="index === items.length - 1" x-on:click="moveDown(index)" class="rounded px-1 text-xs hover:bg-surface-subtle disabled:opacity-30" aria-label="{{ __('baobab::admin.media.gallery_move_down') }}">&darr;</button>
                        <button type="button" x-on:click="remove(index)" class="rounded px-1 text-xs text-danger hover:bg-surface-subtle" aria-label="{{ __('baobab::admin.media.picker_remove_action') }}">&#10005;</button>
                    </div>
                </div>
            </template>
        </div>

        <div class="mt-2">
            <x-baobab::button type="button" variant="secondary" x-on:click="$dispatch('open-modal', 'media-picker-{{ $name }}')">
                {{ __('baobab::admin.media.gallery_add_action') }}
            </x-baobab::button>
        </div>

        <x-baobab::media-picker
            :name="'media-picker-'.$name"
            multiple
            :index-url="route('admin.media.index')"
            :store-url="route('admin.media.store')"
            :chunk-url="route('admin.media.chunk')"
            :csrf-token="csrf_token()"
        />
    </div>

    @error($name)
        <p class="mt-1 text-xs text-danger">{{ $message }}</p>
    @enderror
</div>

@once
    <script>
        function galleryFieldInstance(config) {
            return {
                items: config.items,

                add(selected) {
                    const existingIds = new Set(this.items.map((item) => item.id));

                    for (const item of selected) {
                        if (!existingIds.has(item.id)) {
                            this.items.push(item);
                            existingIds.add(item.id);
                        }
                    }
                },

                remove(index) {
                    this.items.splice(index, 1);
                },

                moveUp(index) {
                    if (index === 0) return;
                    [this.items[index - 1], this.items[index]] = [this.items[index], this.items[index - 1]];
                },

                moveDown(index) {
                    if (index === this.items.length - 1) return;
                    [this.items[index + 1], this.items[index]] = [this.items[index], this.items[index + 1]];
                },
            };
        }
    </script>
@endonce
