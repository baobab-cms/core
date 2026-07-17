@extends('baobab::layouts.admin')

@section('title', __('baobab::admin.menus.create_title'))

@section('content')
    <x-baobab::page :title="__('baobab::admin.menus.create_title')">
        <x-baobab::card>
            <x-baobab::form method="POST" action="{{ route('admin.menus.store') }}">
                <x-baobab::field.text name="name" :label="__('baobab::admin.menus.name_label')" />
                <x-baobab::field.text name="description" :label="__('baobab::admin.menus.description_label')" />

                <x-baobab::button type="submit" variant="primary">
                    {{ __('baobab::admin.menus.create_action') }}
                </x-baobab::button>
            </x-baobab::form>
        </x-baobab::card>
    </x-baobab::page>
@endsection
