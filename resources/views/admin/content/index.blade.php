@extends('baobab::layouts.admin')

@php
    $pageTitle = $contentType->blueprint['label']['plural'] ?? $contentType->key;
@endphp

@section('title', $pageTitle)

@section('content')
    <x-baobab::page :title="$pageTitle">
        <x-slot:actions>
            @if ($canCreate)
                <x-baobab::button :href="route('admin.content.create', ['contentType' => $slug])" variant="primary">
                    {{ __('baobab::admin.content.create_action') }}
                </x-baobab::button>
            @endif
        </x-slot:actions>

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

        <x-baobab::table :columns="$columns" :rows="$rows" :bulk-actions="$bulkActions" />
    </x-baobab::page>
@endsection
