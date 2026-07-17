@extends('baobab::layouts.admin')

@section('title', __('baobab::admin.reading.title'))

@section('content')
    <x-baobab::page :title="__('baobab::admin.reading.title')">
        <x-baobab::card>
            <x-baobab::form method="POST" action="{{ route('admin.reading.update') }}">
                <x-baobab::field.select
                    name="mode"
                    :label="__('baobab::admin.reading.mode_label')"
                    :options="[
                        '' => __('baobab::admin.reading.mode_default'),
                        'static_page' => __('baobab::admin.reading.mode_static_page'),
                        'latest_posts' => __('baobab::admin.reading.mode_latest_posts'),
                    ]"
                    :value="$setting->mode ?? ''"
                />

                <x-baobab::field.select
                    name="page_content_type_key"
                    :label="__('baobab::admin.reading.page_content_type_label')"
                    :options="$contentTypes->pluck('key', 'key')"
                    :value="$setting->page_content_type_key"
                />

                @if (! empty($pageEntries))
                    <x-baobab::field.select
                        name="page_entry_id"
                        :label="__('baobab::admin.reading.page_entry_label')"
                        :options="collect($pageEntries)->pluck('label', 'id')"
                        :value="$setting->page_entry_id"
                    />
                @else
                    <p class="mb-4 text-sm text-muted">{{ __('baobab::admin.reading.no_entries_hint') }}</p>
                @endif

                <x-baobab::field.select
                    name="posts_content_type_key"
                    :label="__('baobab::admin.reading.posts_content_type_label')"
                    :options="$contentTypes->pluck('key', 'key')"
                    :value="$setting->posts_content_type_key"
                />

                <x-baobab::button type="submit" variant="primary">{{ __('baobab::admin.reading.save_action') }}</x-baobab::button>
            </x-baobab::form>
        </x-baobab::card>
    </x-baobab::page>
@endsection
