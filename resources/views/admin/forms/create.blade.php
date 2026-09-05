@extends('baobab::layouts.admin')

@section('title', __('baobab::admin.forms.create_title'))

@section('content')
    <x-baobab::page :title="__('baobab::admin.forms.create_title')">
        <x-baobab::card>
            <x-baobab::form method="POST" action="{{ route('admin.forms.store') }}">
                <x-baobab::field.text name="title" :label="__('baobab::admin.forms.title_label')" />
                <x-baobab::field.text name="slug" :label="__('baobab::admin.forms.slug_label')" />
                <p class="-mt-3 mb-4 text-xs text-muted">{{ __('baobab::admin.forms.slug_help') }}</p>

                <x-baobab::button type="submit" variant="primary">{{ __('baobab::admin.forms.save_action') }}</x-baobab::button>
            </x-baobab::form>
        </x-baobab::card>
    </x-baobab::page>
@endsection
