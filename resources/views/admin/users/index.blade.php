@extends('baobab::layouts.admin')

@section('title', __('baobab::admin.users.title'))

@section('content')
    <x-baobab::page :title="__('baobab::admin.users.title')">
        <x-baobab::table :columns="$columns" :rows="$users" />
    </x-baobab::page>
@endsection
