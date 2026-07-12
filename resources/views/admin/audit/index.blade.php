@extends('baobab::layouts.admin')

@section('title', __('baobab::admin.audit.title'))

@section('content')
    <x-baobab::page :title="__('baobab::admin.audit.title')">
        <form method="GET" class="mb-4 flex flex-wrap items-end gap-2">
            <x-baobab::field.text name="action" label="{{ __('baobab::admin.audit.filter_action') }}" :value="request('action')" />
            <x-baobab::field.text name="actor_id" label="{{ __('baobab::admin.audit.filter_actor') }}" :value="request('actor_id')" />
            <x-baobab::button type="submit" variant="secondary">{{ __('baobab::admin.audit.filter_submit') }}</x-baobab::button>
        </form>

        <x-baobab::table :columns="$columns" :rows="$entries" />
    </x-baobab::page>
@endsection
