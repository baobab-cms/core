@extends('baobab::layouts.admin')

@section('title', __('baobab::admin.seo.title'))

@section('content')
    <x-baobab::page :title="__('baobab::admin.seo.title')">
        <x-baobab::card class="mb-6" :header="__('baobab::admin.seo.global_section_title')">
            <x-baobab::form method="POST" action="{{ route('admin.seo.update') }}">
                <x-baobab::field.text
                    name="site_name"
                    :label="__('baobab::admin.seo.site_name_label')"
                    :value="$setting->site_name"
                    :placeholder="config('app.name')"
                />

                <x-baobab::field.text
                    name="title_separator"
                    :label="__('baobab::admin.seo.title_separator_label')"
                    :value="$setting->title_separator"
                />

                <x-baobab::field.media
                    name="default_share_media_id"
                    :label="__('baobab::admin.seo.default_share_media_label')"
                    type="image"
                    :media="$setting->defaultShareMedia"
                />

                <x-baobab::field.textarea
                    name="default_meta_description"
                    :label="__('baobab::admin.seo.default_meta_description_label')"
                    :value="$setting->default_meta_description"
                />

                <h3 class="mb-2 mt-6 text-sm font-medium text-foreground">{{ __('baobab::admin.seo.robots_section_title') }}</h3>

                <x-baobab::field.textarea
                    name="robots_txt"
                    :label="__('baobab::admin.seo.robots_txt_label')"
                    :value="$setting->robots_txt"
                    placeholder="User-agent: *&#10;Allow: /&#10;Sitemap: {{ url('/sitemap.xml') }}"
                />
                <p class="-mt-3 mb-4 text-xs text-muted">{{ __('baobab::admin.seo.robots_txt_hint') }}</p>

                <x-baobab::field.checkbox
                    name="force_index_on_staging"
                    :label="__('baobab::admin.seo.force_index_on_staging_label')"
                    :checked="$setting->force_index_on_staging"
                />
                <p class="-mt-3 mb-4 text-xs text-muted">{{ __('baobab::admin.seo.force_index_on_staging_hint') }}</p>

                @if ($contentTypes->isNotEmpty())
                    <h3 class="mb-2 mt-6 text-sm font-medium text-foreground">{{ __('baobab::admin.seo.title_templates_title') }}</h3>
                    <p class="mb-4 text-xs text-muted">{{ __('baobab::admin.seo.title_templates_hint') }}</p>

                    @foreach ($contentTypes as $contentType)
                        <div class="mb-4 flex items-end gap-4">
                            <div class="flex-1">
                                <x-baobab::field.text
                                    name="title_templates[{{ $contentType->key }}]"
                                    :label="$contentType->blueprint['label']['singular'] ?? $contentType->key"
                                    :value="$typeSettings[$contentType->key]->title_template"
                                    placeholder="{title} — {site_name}"
                                />
                            </div>

                            <x-baobab::field.checkbox
                                name="exclude_from_sitemap[{{ $contentType->key }}]"
                                :label="__('baobab::admin.seo.exclude_from_sitemap_label')"
                                :checked="$typeSettings[$contentType->key]->exclude_from_sitemap"
                            />
                        </div>
                    @endforeach
                @endif

                <x-baobab::button type="submit" variant="primary">{{ __('baobab::admin.seo.save_action') }}</x-baobab::button>
            </x-baobab::form>
        </x-baobab::card>
    </x-baobab::page>
@endsection
