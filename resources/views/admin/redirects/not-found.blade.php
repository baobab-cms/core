@extends('baobab::layouts.admin')

@section('title', __('baobab::admin.redirects.not_found_title'))

@section('content')
    <x-baobab::page :title="__('baobab::admin.redirects.not_found_title')">
        <x-baobab::table :columns="$columns" :rows="$hits" />
    </x-baobab::page>
@endsection
