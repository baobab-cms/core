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

        <x-baobab::table :columns="$columns" :rows="$users" />
    </x-baobab::page>
@endsection
