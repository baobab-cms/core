@extends('baobab::layouts.admin')

@section('title', __('baobab::admin.audit.title'))

@section('content')
    <x-baobab::page :title="__('baobab::admin.audit.title')">
        <form method="GET" class="mb-4 flex flex-wrap items-end gap-2">
            <x-baobab::field.text name="action" label="{{ __('baobab::admin.audit.filter_action') }}" :value="request('action')" />
            <x-baobab::field.text name="actor_id" label="{{ __('baobab::admin.audit.filter_actor') }}" :value="request('actor_id')" />
            <x-baobab::button type="submit" variant="secondary">{{ __('baobab::admin.audit.filter_submit') }}</x-baobab::button>
        </form>

        @php
            $columns = [
                [
                    'key' => 'created_at',
                    'label' => __('baobab::admin.audit.column_date'),
                    'render' => fn ($entry) => $entry->created_at?->format('Y-m-d H:i'),
                ],
                [
                    'key' => 'actor',
                    'label' => __('baobab::admin.audit.column_actor'),
                    'render' => fn ($entry) => $entry->actor?->name ?? __('baobab::admin.audit.system_actor'),
                ],
                [
                    'key' => 'action',
                    'label' => __('baobab::admin.audit.column_action'),
                ],
                [
                    'key' => 'data',
                    'label' => __('baobab::admin.audit.column_data'),
                    'render' => fn ($entry) => json_encode($entry->data),
                ],
                [
                    'key' => 'ip_address',
                    'label' => __('baobab::admin.audit.column_ip'),
                ],
            ];
        @endphp

        <x-baobab::table :columns="$columns" :rows="$entries" />
    </x-baobab::page>
@endsection
