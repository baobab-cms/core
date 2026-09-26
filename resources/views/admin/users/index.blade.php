@extends('baobab::layouts.admin')

@section('title', __('baobab::admin.users.title'))

@section('content')
    <x-baobab::page :title="__('baobab::admin.users.title')">
        @if ($canInvite)
            <x-slot:actions>
                <x-baobab::button :href="route('admin.users.create')" variant="primary">
                    {{ __('baobab::admin.users.invite.action') }}
                </x-baobab::button>
            </x-slot:actions>
        @endif

        <form method="GET" action="{{ route('admin.users.index') }}" class="mb-6 flex flex-wrap items-end gap-2">
            <x-baobab::field.select
                name="status"
                label="{{ __('baobab::admin.users.status.filter_label') }}"
                :options="$statusOptions"
                :value="$status"
            />
            <x-baobab::button type="submit" variant="secondary">{{ __('baobab::admin.users.status.filter_submit') }}</x-baobab::button>
        </form>

        <x-baobab::table :columns="$columns" :rows="$users" />
    </x-baobab::page>
@endsection
