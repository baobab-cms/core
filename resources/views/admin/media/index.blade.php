@extends('baobab::layouts.admin')

@section('title', __('baobab::admin.media.title'))

@push('admin.head')
    <meta name="csrf-token" content="{{ csrf_token() }}">
@endpush

@section('content')
    <x-baobab::page :title="__('baobab::admin.media.title')">
        <div
            x-data="mediaLibrary({
                storeUrl: @js(route('admin.media.store')),
                chunkUrl: @js(route('admin.media.chunk')),
                chunkThreshold: {{ (int) config('baobab.media.chunk_threshold') }},
                chunkSize: {{ (int) config('baobab.media.chunk_size') }},
                currentFolderId: {{ $currentFolder?->id ?? 'null' }},
                csrfToken: @js(csrf_token()),
                externalStoreUrl: @js(route('admin.media.external.store')),
            })"
        >
            {{-- Fil d'Ariane --}}
            @unless ($trashed)
                <nav class="mb-4 flex items-center gap-1 text-sm text-muted">
                    <a href="{{ route('admin.media.index') }}" class="hover:text-foreground">{{ __('baobab::admin.media.root_folder') }}</a>
                    @foreach ($breadcrumb as $crumb)
                        <span aria-hidden="true">/</span>
                        <a href="{{ route('admin.media.index', ['folder' => $crumb->id]) }}" class="hover:text-foreground">{{ $crumb->name }}</a>
                    @endforeach
                </nav>
            @endunless

            <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                <form method="GET" action="{{ route('admin.media.index') }}" class="flex flex-wrap items-end gap-2">
                    @if ($currentFolder)
                        <input type="hidden" name="folder" value="{{ $currentFolder->id }}">
                    @endif
                    @if ($trashed)
                        <input type="hidden" name="trashed" value="1">
                    @endif
                    <x-baobab::field.text name="q" label="" :value="request('q')" placeholder="{{ __('baobab::admin.media.search_placeholder') }}" />
                    <x-baobab::field.select
                        name="type"
                        :options="['' => __('baobab::admin.media.filter_all_types'), 'image' => 'image', 'video' => 'video', 'audio' => 'audio', 'application' => 'document']"
                        :value="request('type')"
                    />
                    <label class="mb-4 flex items-center gap-1 text-sm text-foreground">
                        <input type="checkbox" name="unused" value="1" @checked($unused)>
                        {{ __('baobab::admin.media.unused_filter_label') }}
                    </label>
                    <x-baobab::button type="submit" variant="secondary">{{ __('baobab::admin.media.search_submit') }}</x-baobab::button>
                </form>

                <div class="flex items-center gap-2">
                    @if ($trashed)
                        <x-baobab::button variant="secondary" :href="route('admin.media.index')">
                            {{ __('baobab::admin.media.back_to_library_action') }}
                        </x-baobab::button>
                    @else
                        <x-baobab::button variant="secondary" :href="route('admin.media.index', ['trashed' => 1])">
                            {{ __('baobab::admin.media.trash_action') }}
                        </x-baobab::button>
                    @endif

                    @if ($canUpload && ! $trashed)
                        <x-baobab::button type="button" variant="secondary" x-on:click="$dispatch('open-modal', 'new-external-media')">
                            {{ __('baobab::admin.media.external_action') }}
                        </x-baobab::button>
                        <x-baobab::button type="button" variant="secondary" x-on:click="$dispatch('open-modal', 'new-media-folder')">
                            {{ __('baobab::admin.media.new_folder_action') }}
                        </x-baobab::button>
                    @endif
                </div>
            </div>

            @if ($canUpload && ! $trashed)
                {{-- Zone de dépôt --}}
                <div
                    class="mb-6 rounded-lg border-2 border-dashed border-border p-6 text-center text-sm text-muted"
                    x-bind:class="{ 'border-primary bg-surface-subtle': dragging }"
                    x-on:dragover.prevent="dragging = true"
                    x-on:dragleave.prevent="dragging = false"
                    x-on:drop.prevent="dragging = false; onDrop($event)"
                >
                    <p>{{ __('baobab::admin.media.dropzone_hint') }}</p>
                    <input type="file" multiple x-ref="fileInput" class="hidden" x-on:change="onFileInput($event)" @if (! $canUploadSvg) accept="image/jpeg,image/png,image/gif,image/webp,application/pdf,video/mp4,video/webm,audio/mpeg,audio/wav" @endif>
                    <x-baobab::button type="button" variant="secondary" class="mt-3" x-on:click="$refs.fileInput.click()">
                        {{ __('baobab::admin.media.upload_action') }}
                    </x-baobab::button>
                </div>

                {{-- File d'upload --}}
                <template x-if="uploading.length > 0">
                    <div class="mb-6 space-y-2">
                        <template x-for="item in uploading" :key="item.id">
                            <div class="flex items-center gap-3 rounded-md border border-border bg-surface px-3 py-2 text-sm">
                                <span class="flex-1 truncate" x-text="item.name"></span>
                                <span class="text-xs text-muted" x-show="item.status === 'uploading'" x-text="item.progress + '%'"></span>
                                <span class="text-xs text-danger" x-show="item.status === 'error'">{{ __('baobab::admin.media.upload_failed') }}</span>
                            </div>
                        </template>
                    </div>
                </template>
            @endif

            {{-- Sous-dossiers --}}
            @if ($folders->isNotEmpty())
                <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-6">
                    @foreach ($folders as $folder)
                        <a
                            href="{{ route('admin.media.index', ['folder' => $folder->id]) }}"
                            class="rounded-md border border-border bg-surface px-3 py-4 text-center text-sm text-foreground hover:bg-surface-subtle"
                        >
                            {{ $folder->name }}
                        </a>
                    @endforeach
                </div>
            @endif

            {{-- Grille de médias --}}
            @if ($media->isEmpty())
                <x-baobab::empty-state :message="$trashed ? __('baobab::admin.media.trash_empty') : __('baobab::admin.media.empty')" />
            @else
                {{--
                    Formulaire d'actions groupées détaché de la grille (pas englobant) — les
                    éléments individuels (restaurer/purger en mode corbeille) rendent leurs
                    propres formulaires, et imbriquer un <form> dans un autre est invalide en
                    HTML (cf. table.blade.php). Cases à cocher et bouton s'y rattachent via
                    l'attribut form="".
                --}}
                <form id="media-bulk-actions" method="POST" action="{{ $trashed ? route('admin.media.bulk-restore') : route('admin.media.move') }}">
                    @csrf
                </form>

                <div class="mb-4 flex flex-wrap items-center gap-2">
                    @if ($trashed)
                        <button type="submit" form="media-bulk-actions" formaction="{{ route('admin.media.bulk-restore') }}" class="rounded-md border border-border bg-surface px-2 py-1 text-xs font-medium text-foreground hover:bg-surface-subtle">
                            {{ __('baobab::admin.media.bulk_restore_action') }}
                        </button>
                        <button
                            type="submit"
                            form="media-bulk-actions"
                            formaction="{{ route('admin.media.bulk-force-destroy') }}"
                            onclick="return confirm('{{ __('baobab::admin.media.bulk_purge_confirm_title') }}')"
                            class="rounded-md border border-danger/30 bg-surface px-2 py-1 text-xs font-medium text-danger hover:bg-danger/5"
                        >
                            {{ __('baobab::admin.media.bulk_purge_action') }}
                        </button>
                    @else
                        <select name="folder_id" form="media-bulk-actions" class="rounded-md border border-border bg-surface px-2 py-1 text-sm">
                            <option value="">{{ __('baobab::admin.media.root_folder') }}</option>
                            @foreach ($allFolders as $folder)
                                <option value="{{ $folder->id }}">{{ $folder->name }}</option>
                            @endforeach
                        </select>
                        <button type="submit" form="media-bulk-actions" formaction="{{ route('admin.media.move') }}" class="rounded-md border border-border bg-surface px-2 py-1 text-xs font-medium text-foreground hover:bg-surface-subtle">
                            {{ __('baobab::admin.media.move_to_folder_action') }}
                        </button>
                        <button type="submit" form="media-bulk-actions" formaction="{{ route('admin.media.bulk-delete') }}" class="rounded-md border border-border bg-surface px-2 py-1 text-xs font-medium text-foreground hover:bg-surface-subtle">
                            {{ __('baobab::admin.media.bulk_trash_action') }}
                        </button>
                    @endif
                </div>

                <div class="grid w-full grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6">
                    @foreach ($media as $item)
                        <div class="group relative block overflow-hidden rounded-lg border border-border bg-surface">
                            <label class="absolute left-2 top-2 z-10 rounded bg-surface/80 p-0.5">
                                <input type="checkbox" name="ids[]" value="{{ $item->id }}" form="media-bulk-actions">
                            </label>

                            @if ($trashed)
                                @if ($item->isImage() || $item->isExternal())
                                    <img src="{{ $item->url() }}" alt="{{ $item->alt ?? $item->file_name }}" class="h-24 w-full object-cover">
                                @else
                                    <div class="flex h-24 w-full items-center justify-center bg-surface-subtle text-xs text-muted">
                                        {{ strtoupper(pathinfo($item->file_name, PATHINFO_EXTENSION)) }}
                                    </div>
                                @endif

                                <span class="block truncate px-2 py-1 text-xs text-foreground">{{ $item->file_name }}</span>

                                <div class="flex gap-2 px-2 pb-2">
                                    <form method="POST" action="{{ route('admin.media.restore', ['media' => $item->id]) }}">
                                        @csrf
                                        <button type="submit" class="text-xs font-medium text-primary hover:underline">
                                            {{ __('baobab::admin.media.restore_action') }}
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.media.force-destroy', ['media' => $item->id]) }}" onsubmit="return confirm('{{ __('baobab::admin.media.purge_confirm_title') }}')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-xs font-medium text-danger hover:underline">
                                            {{ __('baobab::admin.media.purge_action') }}
                                        </button>
                                    </form>
                                </div>
                            @else
                                <a href="{{ route('admin.media.show', ['media' => $item->id]) }}" class="block" title="{{ __('baobab::admin.media.view_action') }}">
                                    @if ($item->isExternal())
                                        <span class="absolute right-2 top-2 z-10 rounded bg-foreground/70 px-1 py-0.5 text-[10px] font-medium uppercase text-surface">
                                            {{ __('baobab::admin.media.external_badge') }}
                                        </span>
                                    @endif
                                    @if ($item->isImage() || $item->isExternal())
                                        <img
                                            src="{{ $item->url() }}"
                                            alt="{{ $item->alt ?? $item->file_name }}"
                                            class="h-24 w-full object-cover"
                                        >
                                    @else
                                        <div class="flex h-24 w-full items-center justify-center bg-surface-subtle text-xs text-muted">
                                            {{ strtoupper(pathinfo($item->file_name, PATHINFO_EXTENSION)) }}
                                        </div>
                                    @endif

                                    <span class="block truncate px-2 py-1 text-xs text-foreground">{{ $item->file_name }}</span>
                                </a>
                            @endif
                        </div>
                    @endforeach
                </div>

                <div class="mt-4">{{ $media->links() }}</div>
            @endif

            {{-- Média externe (oEmbed) --}}
            <x-baobab::modal name="new-external-media" :title="__('baobab::admin.media.external_action')">
                <p class="mb-3 text-sm text-muted">{{ __('baobab::admin.media.external_hint') }}</p>
                <label class="block text-sm font-medium text-foreground" for="external-media-url">{{ __('baobab::admin.media.external_url_label') }}</label>
                <input
                    id="external-media-url"
                    type="url"
                    x-model="externalUrl"
                    x-on:keydown.enter.prevent="submitExternal()"
                    placeholder="https://www.youtube.com/watch?v=…"
                    class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm text-foreground"
                >
                <template x-if="externalError">
                    <p class="mt-2 text-sm text-danger" x-text="externalError"></p>
                </template>
                <div class="mt-4 flex justify-end">
                    <x-baobab::button type="button" variant="primary" x-bind:disabled="externalSubmitting" x-on:click="submitExternal()">
                        {{ __('baobab::admin.media.external_submit_action') }}
                    </x-baobab::button>
                </div>
            </x-baobab::modal>

            {{-- Nouveau dossier --}}
            <x-baobab::modal name="new-media-folder" :title="__('baobab::admin.media.new_folder_action')">
                <x-baobab::form method="POST" action="{{ route('admin.media.folders.store') }}">
                    @if ($currentFolder)
                        <input type="hidden" name="parent_id" value="{{ $currentFolder->id }}">
                    @endif
                    <x-baobab::field.text name="name" label="{{ __('baobab::admin.media.new_folder_name_label') }}" />
                    <x-baobab::button type="submit" variant="primary">{{ __('baobab::admin.content.save_action') }}</x-baobab::button>
                </x-baobab::form>
            </x-baobab::modal>

            {{-- Doublon détecté --}}
            <template x-if="duplicate">
                <div class="fixed inset-0 z-50 flex items-center justify-center bg-foreground/50 px-4">
                    <div class="w-full max-w-sm rounded-lg border border-border bg-surface p-6 shadow-lg">
                        <h2 class="font-display text-base font-semibold text-foreground">{{ __('baobab::admin.media.duplicate_found_title') }}</h2>
                        <p class="mt-2 text-sm text-muted">{{ __('baobab::admin.media.duplicate_found_description') }}</p>
                        <div class="mt-4 flex justify-end gap-2">
                            <x-baobab::button type="button" variant="secondary" x-on:click="resolveDuplicate('reuse')">
                                {{ __('baobab::admin.media.duplicate_reuse_action') }}
                            </x-baobab::button>
                            <x-baobab::button type="button" variant="primary" x-on:click="resolveDuplicate('new')">
                                {{ __('baobab::admin.media.duplicate_upload_anyway_action') }}
                            </x-baobab::button>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </x-baobab::page>

    @once
        <script>
            function mediaLibrary(config) {
                return {
                    dragging: false,
                    uploading: [],
                    duplicate: null,
                    externalUrl: '',
                    externalError: null,
                    externalSubmitting: false,

                    async submitExternal() {
                        if (!this.externalUrl || this.externalSubmitting) return;

                        this.externalSubmitting = true;
                        this.externalError = null;

                        try {
                            const body = new FormData();
                            body.append('url', this.externalUrl);
                            if (config.currentFolderId) body.append('folder_id', config.currentFolderId);

                            const response = await fetch(config.externalStoreUrl, {
                                method: 'POST',
                                headers: { 'X-CSRF-TOKEN': config.csrfToken, Accept: 'application/json' },
                                body,
                            });

                            if (!response.ok) {
                                const payload = await response.json().catch(() => null);
                                this.externalError = (payload && payload.message) || '{{ __('baobab::admin.media.external_failed') }}';
                                return;
                            }

                            window.location.reload();
                        } catch (error) {
                            this.externalError = '{{ __('baobab::admin.media.external_failed') }}';
                        } finally {
                            this.externalSubmitting = false;
                        }
                    },

                    onDrop(event) {
                        [...event.dataTransfer.files].forEach((file) => this.enqueue(file));
                    },

                    onFileInput(event) {
                        [...event.target.files].forEach((file) => this.enqueue(file));
                        event.target.value = '';
                    },

                    enqueue(file) {
                        const item = { id: crypto.randomUUID(), name: file.name, progress: 0, status: 'pending' };
                        this.uploading.push(item);
                        this.upload(file, item, null);
                    },

                    async upload(file, item, duplicateAction) {
                        item.status = 'uploading';

                        try {
                            if (file.size > config.chunkThreshold) {
                                await this.uploadChunked(file, item, duplicateAction);
                            } else {
                                await this.uploadDirect(file, item, duplicateAction);
                            }

                            item.status = 'done';
                            item.progress = 100;
                            window.location.reload();
                        } catch (error) {
                            if (error && error.duplicate) {
                                this.duplicate = { file, item, ...error.duplicate };
                                return;
                            }

                            item.status = 'error';
                        }
                    },

                    async uploadDirect(file, item, duplicateAction) {
                        const body = new FormData();
                        body.append('file', file);
                        if (config.currentFolderId) body.append('folder_id', config.currentFolderId);
                        if (duplicateAction) body.append('duplicate_action', duplicateAction);

                        const response = await fetch(config.storeUrl, {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': config.csrfToken, Accept: 'application/json' },
                            body,
                        });

                        if (response.status === 409) {
                            throw { duplicate: await response.json() };
                        }

                        if (!response.ok) {
                            throw new Error('upload failed');
                        }

                        item.progress = 100;
                    },

                    async uploadChunked(file, item, duplicateAction) {
                        const uploadId = crypto.randomUUID();
                        const totalChunks = Math.ceil(file.size / config.chunkSize);

                        for (let index = 0; index < totalChunks; index++) {
                            const start = index * config.chunkSize;
                            const chunk = file.slice(start, start + config.chunkSize);

                            let attempts = 0;

                            while (true) {
                                try {
                                    const body = new FormData();
                                    body.append('chunk', chunk, file.name);
                                    body.append('upload_id', uploadId);
                                    body.append('chunk_index', index);
                                    body.append('total_chunks', totalChunks);
                                    body.append('file_name', file.name);
                                    if (config.currentFolderId) body.append('folder_id', config.currentFolderId);
                                    if (duplicateAction) body.append('duplicate_action', duplicateAction);

                                    const response = await fetch(config.chunkUrl, {
                                        method: 'POST',
                                        headers: { 'X-CSRF-TOKEN': config.csrfToken, Accept: 'application/json' },
                                        body,
                                    });

                                    if (response.status === 409) {
                                        throw { duplicate: await response.json() };
                                    }

                                    if (!response.ok) {
                                        throw new Error('chunk failed');
                                    }

                                    item.progress = Math.round(((index + 1) / totalChunks) * 100);
                                    break;
                                } catch (error) {
                                    if (error && error.duplicate) {
                                        throw error;
                                    }

                                    attempts++;

                                    if (attempts >= 3) {
                                        throw error;
                                    }
                                }
                            }
                        }
                    },

                    resolveDuplicate(action) {
                        const { file, item } = this.duplicate;
                        this.duplicate = null;
                        this.upload(file, item, action);
                    },
                };
            }
        </script>
    @endonce
@endsection
