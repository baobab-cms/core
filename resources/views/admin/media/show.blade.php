@extends('baobab::layouts.admin')

@section('title', $media->file_name)

@push('admin.head')
    <meta name="csrf-token" content="{{ csrf_token() }}">
@endpush

@section('content')
    <x-baobab::page
        :title="$media->file_name"
        :breadcrumbs="[[__('baobab::admin.media.root_folder'), route('admin.media.index', ['folder' => $media->folder_id])], [$media->file_name]]"
    >
        <x-slot:actions>
            <x-baobab::button variant="secondary" :href="$media->url()" target="_blank" rel="noopener">
                {{ __('baobab::admin.media.show.open_file_action') }}
            </x-baobab::button>

            @if ($canDelete)
                <form
                    method="POST"
                    action="{{ route('admin.media.destroy', ['media' => $media->id]) }}"
                    onsubmit="return confirm('{{ __('baobab::admin.media.show.delete_confirm_title') }}')"
                >
                    @csrf
                    @method('DELETE')
                    <x-baobab::button type="submit" variant="danger">
                        {{ __('baobab::admin.media.show.delete_action') }}
                    </x-baobab::button>
                </form>
            @endif
        </x-slot:actions>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <div class="space-y-6">
                <x-baobab::card>
                    <p class="text-sm text-muted">
                        {{ $media->mime_type }} · {{ number_format($media->size / 1024, 0) }} Ko
                        @if ($media->currentWidth() && $media->currentHeight())
                            · {{ __('baobab::admin.media.show.current_dimensions', ['width' => $media->currentWidth(), 'height' => $media->currentHeight()]) }}
                        @endif
                    </p>
                </x-baobab::card>

                <x-baobab::card :header="__('baobab::admin.media.show.metadata_title')">
                    <x-baobab::form
                        method="PATCH"
                        action="{{ route('admin.media.update', ['media' => $media->id]) }}"
                    >
                        <x-baobab::field.text name="title" :label="__('baobab::admin.media.show.title_label')" :value="$media->title" :disabled="! $canUpdate" />
                        <x-baobab::field.text name="alt" :label="__('baobab::admin.media.show.alt_label')" :value="$media->alt" :disabled="! $canUpdate" />
                        <x-baobab::field.text name="caption" :label="__('baobab::admin.media.show.caption_label')" :value="$media->caption" :disabled="! $canUpdate" />
                        <x-baobab::field.textarea name="description" :label="__('baobab::admin.media.show.description_label')" :value="$media->description" :disabled="! $canUpdate" />

                        @if ($canUpdate)
                            <x-baobab::button type="submit" variant="primary">
                                {{ __('baobab::admin.media.show.save_metadata_action') }}
                            </x-baobab::button>
                        @endif
                    </x-baobab::form>
                </x-baobab::card>
            </div>

            @if ($media->isRasterImage() && $canUpdate)
                <div
                    class="space-y-6"
                    x-data="mediaEditor({
                        transformUrl: @js(route('admin.media.transform.store', ['media' => $media->id])),
                        focalPointUrl: @js(route('admin.media.focal-point.update', ['media' => $media->id])),
                        csrfToken: @js(csrf_token()),
                        focalX: {{ $media->focal_x ?? 0.5 }},
                        focalY: {{ $media->focal_y ?? 0.5 }},
                    })"
                >
                    <x-baobab::card :header="__('baobab::admin.media.show.focal_point_title')">
                        <p class="mb-3 text-sm text-muted">{{ __('baobab::admin.media.show.focal_point_hint') }}</p>

                        <div class="relative inline-block">
                            <img
                                src="{{ $media->url() }}"
                                alt="{{ $media->alt ?? $media->file_name }}"
                                class="max-h-80 max-w-full cursor-crosshair select-none rounded-md border border-border"
                                x-on:click="setFocalPoint($event); saveFocalPoint()"
                            >
                            <div
                                class="pointer-events-none absolute h-4 w-4 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white bg-primary shadow"
                                x-bind:style="`left: ${focalX * 100}%; top: ${focalY * 100}%`"
                            ></div>
                        </div>

                        <p class="mt-2 text-xs text-muted" x-show="savingFocalPoint">{{ __('baobab::admin.media.show.saving') }}</p>
                    </x-baobab::card>

                    <x-baobab::card :header="__('baobab::admin.media.show.editing_title')">
                        <img x-ref="cropTarget" src="{{ $media->url() }}" alt="{{ $media->alt ?? $media->file_name }}" class="block max-w-full" x-init="initCropper()">

                        <div class="mt-4 flex flex-wrap items-center gap-2">
                            <x-baobab::button type="button" variant="secondary" x-on:click="rotate(-90)">
                                {{ __('baobab::admin.media.show.rotate_left_action') }}
                            </x-baobab::button>
                            <x-baobab::button type="button" variant="secondary" x-on:click="rotate(90)">
                                {{ __('baobab::admin.media.show.rotate_right_action') }}
                            </x-baobab::button>
                            <x-baobab::button type="button" variant="secondary" x-on:click="flipHorizontal()">
                                {{ __('baobab::admin.media.show.flip_horizontal_action') }}
                            </x-baobab::button>
                            <x-baobab::button type="button" variant="secondary" x-on:click="flipVertical()">
                                {{ __('baobab::admin.media.show.flip_vertical_action') }}
                            </x-baobab::button>
                            <x-baobab::button type="button" variant="primary" x-on:click="applyTransform()" x-bind:disabled="savingTransform">
                                {{ __('baobab::admin.media.show.apply_edit_action') }}
                            </x-baobab::button>

                            @if ($media->edited_path)
                                <x-baobab::button type="button" variant="danger" x-on:click="restoreOriginal()" x-bind:disabled="restoring">
                                    {{ __('baobab::admin.media.show.restore_original_action') }}
                                </x-baobab::button>
                            @endif
                        </div>
                    </x-baobab::card>
                </div>
            @endif
        </div>
    </x-baobab::page>

    @once
        <script>
            function mediaEditor(config) {
                return {
                    cropper: null,
                    focalX: config.focalX,
                    focalY: config.focalY,
                    savingFocalPoint: false,
                    savingTransform: false,
                    restoring: false,

                    initCropper() {
                        this.cropper = new Cropper(this.$refs.cropTarget, {
                            viewMode: 1,
                            autoCropArea: 1,
                            background: false,
                        });
                    },

                    rotate(degrees) {
                        this.cropper?.rotate(degrees);
                    },

                    flipHorizontal() {
                        const data = this.cropper.getData();
                        this.cropper.scaleX(-(data.scaleX || 1));
                    },

                    flipVertical() {
                        const data = this.cropper.getData();
                        this.cropper.scaleY(-(data.scaleY || 1));
                    },

                    async applyTransform() {
                        this.savingTransform = true;

                        try {
                            const canvas = this.cropper.getCroppedCanvas();
                            const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.92));

                            const body = new FormData();
                            body.append('file', blob, 'edit.jpg');

                            const response = await fetch(config.transformUrl, {
                                method: 'POST',
                                headers: { 'X-CSRF-TOKEN': config.csrfToken, Accept: 'application/json' },
                                body,
                            });

                            if (!response.ok) {
                                throw new Error('transform failed');
                            }

                            window.location.reload();
                        } finally {
                            this.savingTransform = false;
                        }
                    },

                    async restoreOriginal() {
                        if (!confirm(@js(__('baobab::admin.media.show.restore_original_confirm_description')))) {
                            return;
                        }

                        this.restoring = true;

                        try {
                            const response = await fetch(config.transformUrl, {
                                method: 'DELETE',
                                headers: { 'X-CSRF-TOKEN': config.csrfToken, Accept: 'application/json' },
                            });

                            if (!response.ok) {
                                throw new Error('restore failed');
                            }

                            window.location.reload();
                        } finally {
                            this.restoring = false;
                        }
                    },

                    setFocalPoint(event) {
                        const rect = event.target.getBoundingClientRect();
                        this.focalX = Math.min(1, Math.max(0, (event.clientX - rect.left) / rect.width));
                        this.focalY = Math.min(1, Math.max(0, (event.clientY - rect.top) / rect.height));
                    },

                    async saveFocalPoint() {
                        this.savingFocalPoint = true;

                        try {
                            await fetch(config.focalPointUrl, {
                                method: 'PATCH',
                                headers: {
                                    'X-CSRF-TOKEN': config.csrfToken,
                                    Accept: 'application/json',
                                    'Content-Type': 'application/json',
                                },
                                body: JSON.stringify({ focal_x: this.focalX, focal_y: this.focalY }),
                            });
                        } finally {
                            this.savingFocalPoint = false;
                        }
                    },
                };
            }
        </script>
    @endonce
@endsection
