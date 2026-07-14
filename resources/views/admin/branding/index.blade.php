@extends('baobab::layouts.admin')

@section('title', __('baobab::admin.branding.title'))

@section('content')
    <x-baobab::page :title="__('baobab::admin.branding.title')">
        <x-baobab::card>
            <x-baobab::form method="POST" action="{{ route('admin.branding.update') }}">
                <x-baobab::field.media
                    name="logo_media_id"
                    label="{{ __('baobab::admin.branding.logo_label') }}"
                    type="image"
                    :media="$setting->logo"
                />

                <x-baobab::field.media
                    name="favicon_media_id"
                    label="{{ __('baobab::admin.branding.favicon_label') }}"
                    type="image"
                    :media="$setting->favicon"
                />

                <x-baobab::field.text
                    type="color"
                    name="primary_color"
                    label="{{ __('baobab::admin.branding.primary_color_label') }}"
                    :value="$setting->primary_color ?? '#0f766e'"
                />

                <x-baobab::button type="submit" variant="primary">{{ __('baobab::admin.branding.save_action') }}</x-baobab::button>
            </x-baobab::form>
        </x-baobab::card>
    </x-baobab::page>
@endsection
