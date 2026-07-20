@extends('baobab::layouts.admin')

@section('title', __('baobab::admin.webhooks.title'))

@section('content')
    <x-baobab::page :title="__('baobab::admin.webhooks.title')">
        <div class="mb-4 flex justify-end">
            <x-baobab::button :href="route('admin.webhooks.create')" variant="primary">
                {{ __('baobab::admin.webhooks.create_title') }}
            </x-baobab::button>
        </div>

        <x-baobab::table :columns="$columns" :rows="$subscriptions" />
    </x-baobab::page>
@endsection
