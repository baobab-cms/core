@extends('baobab::layouts.admin')

@section('title', __('baobab::admin.forms.title'))

@section('content')
    <x-baobab::page :title="__('baobab::admin.forms.title')">
        <div class="mb-4 flex justify-end">
            <x-baobab::button :href="route('admin.forms.create')" variant="primary">
                {{ __('baobab::admin.forms.create_title') }}
            </x-baobab::button>
        </div>

        <x-baobab::table :columns="$columns" :rows="$forms" />
    </x-baobab::page>
@endsection
