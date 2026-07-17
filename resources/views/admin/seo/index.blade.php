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

                @if ($contentTypes->isNotEmpty())
                    <h3 class="mb-2 mt-6 text-sm font-medium text-foreground">{{ __('baobab::admin.seo.title_templates_title') }}</h3>
                    <p class="mb-4 text-xs text-muted">{{ __('baobab::admin.seo.title_templates_hint') }}</p>

                    @foreach ($contentTypes as $contentType)
                        <x-baobab::field.text
                            name="title_templates[{{ $contentType->key }}]"
                            :label="$contentType->blueprint['label']['singular'] ?? $contentType->key"
                            :value="$titleTemplates[$contentType->key] ?? null"
                            placeholder="{title} — {site_name}"
                        />
                    @endforeach
                @endif

                <x-baobab::button type="submit" variant="primary">{{ __('baobab::admin.seo.save_action') }}</x-baobab::button>
            </x-baobab::form>
        </x-baobab::card>
    </x-baobab::page>
@endsection
