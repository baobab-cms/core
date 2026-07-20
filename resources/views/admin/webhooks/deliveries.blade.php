@extends('baobab::layouts.admin')

@section('title', __('baobab::admin.webhooks.deliveries_title'))

@section('content')
    <x-baobab::page :title="__('baobab::admin.webhooks.deliveries_title')">
        <p class="mb-4 text-sm text-muted">{{ $subscription->url }}</p>

        <x-baobab::table :columns="$columns" :rows="$deliveries" />
    </x-baobab::page>
@endsection
