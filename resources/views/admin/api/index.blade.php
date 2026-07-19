@extends('baobab::layouts.admin')

@section('title', __('baobab::admin.api.title'))

@section('content')
    <x-baobab::page :title="__('baobab::admin.api.title')">
        <x-baobab::card>
            <x-baobab::form method="POST" action="{{ route('admin.api.update') }}">
                <x-baobab::field.checkbox
                    name="rest_enabled"
                    label="{{ __('baobab::admin.api.rest_enabled_label') }}"
                    :checked="$setting->rest_enabled"
                />

                <x-baobab::field.text
                    type="number"
                    min="1"
                    name="rate_limit_per_minute"
                    label="{{ __('baobab::admin.api.rate_limit_label') }}"
                    :value="$setting->rate_limit_per_minute"
                />

                <x-baobab::field.textarea
                    name="allowed_origins"
                    label="{{ __('baobab::admin.api.allowed_origins_label') }}"
                    :value="$setting->allowed_origins"
                />
                <p class="-mt-3 mb-4 text-xs text-muted">{{ __('baobab::admin.api.allowed_origins_help') }}</p>

                <x-baobab::button type="submit" variant="primary">{{ __('baobab::admin.api.save_action') }}</x-baobab::button>
            </x-baobab::form>
        </x-baobab::card>
    </x-baobab::page>
@endsection
