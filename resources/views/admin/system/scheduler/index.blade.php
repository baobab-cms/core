@extends('baobab::layouts.admin')

@section('title', __('baobab::admin.scheduler.title'))

@section('content')
    <x-baobab::page :title="__('baobab::admin.scheduler.title')">
        <p class="mb-4 text-sm text-muted">{{ __('baobab::admin.scheduler.intro') }}</p>

        <x-baobab::table :columns="$columns" :rows="$tasks" row-key="taskKey" />
    </x-baobab::page>
@endsection
