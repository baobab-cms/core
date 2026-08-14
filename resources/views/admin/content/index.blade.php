@extends('baobab::layouts.admin')

@section('title', $pageTitle)

@section('content')
    <x-baobab::page :title="$pageTitle">
        <x-slot:actions>
            @if ($trashed)
                <x-baobab::button :href="route('admin.content.index', ['contentType' => $slug])" variant="secondary">
                    {{ __('baobab::admin.content.back_to_list_action') }}
                </x-baobab::button>
            @else
                <x-baobab::button :href="route('admin.content.index', ['contentType' => $slug, 'trashed' => 1])" variant="secondary">
                    {{ __('baobab::admin.content.trash_action') }}
                </x-baobab::button>

                @if ($canCreate)
                    <x-baobab::button :href="route('admin.content.create', ['contentType' => $slug])" variant="primary">
                        {{ __('baobab::admin.content.create_action') }}
                    </x-baobab::button>
                @endif
            @endif
        </x-slot:actions>

        @unless ($trashed)
            <form method="GET" action="{{ route('admin.content.index', ['contentType' => $slug]) }}" class="mb-4 flex flex-wrap items-end gap-2">
                <x-baobab::field.text name="q" label="" :value="request('q')" placeholder="{{ __('baobab::admin.content.search_placeholder') }}" />

                <x-baobab::field.select
                    name="status"
                    label="{{ __('baobab::admin.content.filter_status') }}"
                    :options="['' => __('baobab::admin.content.filter_all_statuses')] + array_combine($statuses, $statuses)"
                    :value="request('status')"
                />

                <x-baobab::button type="submit" variant="secondary">
                    {{ __('baobab::admin.content.search_submit') }}
                </x-baobab::button>
            </form>
        @endunless

        @if ($trashed && empty($rows->items()))
            <x-baobab::empty-state :message="__('baobab::admin.content.trash_empty')" />
        @else
            <x-baobab::table :columns="$columns" :rows="$rows" :bulk-actions="$bulkActions" />
        @endif
    </x-baobab::page>
@endsection
