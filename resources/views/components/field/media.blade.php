@props([
    'name',
    'label' => null,
    'type' => 'file',
    'media' => null,
])

<div class="mb-4">
    @if ($label)
        <label class="mb-1 block text-sm font-medium text-foreground">{{ $label }}</label>
    @endif

    <div
        x-data="mediaFieldInstance({
            selected: @js($media ? ['id' => $media->id, 'url' => $media->url(), 'file_name' => $media->file_name, 'mime_type' => $media->mime_type, 'alt' => $media->alt] : null),
        })"
        x-on:media-selected="select($event.detail)"
    >
        <input type="hidden" name="{{ $name }}" x-bind:value="selected ? selected.id : ''">

        <div class="flex items-center gap-3">
            <template x-if="selected">
                <div class="flex items-center gap-3">
                    <template x-if="selected.mime_type && selected.mime_type.startsWith('image/')">
                        <img :src="selected.url" :alt="selected.alt || selected.file_name" class="h-16 w-16 rounded-md border border-border object-cover">
                    </template>
                    <template x-if="!selected.mime_type || !selected.mime_type.startsWith('image/')">
                        <div class="flex h-16 w-16 items-center justify-center rounded-md border border-border bg-surface-subtle text-xs text-muted">
                            {{ __('baobab::admin.media.picker_file_label') }}
                        </div>
                    </template>
                    <span class="text-sm text-foreground" x-text="selected.file_name"></span>
                </div>
            </template>

            <div class="flex gap-2">
                <x-baobab::button type="button" variant="secondary" x-on:click="$dispatch('open-modal', 'media-picker-{{ $name }}')">
                    <span x-text="selected ? @js(__('baobab::admin.media.picker_change_action')) : @js(__('baobab::admin.media.picker_choose_action'))"></span>
                </x-baobab::button>

                <x-baobab::button type="button" variant="ghost" x-show="selected" x-on:click="clear()">
                    {{ __('baobab::admin.media.picker_remove_action') }}
                </x-baobab::button>
            </div>
        </div>

        <x-baobab::media-picker
            :name="'media-picker-'.$name"
            :type="$type === 'image' ? 'image' : null"
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
        function mediaFieldInstance(config) {
            return {
                selected: config.selected,

                select(item) {
                    this.selected = item;
                },

                clear() {
                    this.selected = null;
                },
            };
        }
    </script>
@endonce
