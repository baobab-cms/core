@extends('baobab::layouts.admin')

@section('title', __('baobab::admin.webhooks.create_title'))

@section('content')
    <x-baobab::page :title="__('baobab::admin.webhooks.create_title')">
        <x-baobab::card>
            <x-baobab::form method="POST" action="{{ route('admin.webhooks.store') }}">
                <x-baobab::field.text name="url" :label="__('baobab::admin.webhooks.url_label')" />
                <x-baobab::field.text name="secret" :label="__('baobab::admin.webhooks.secret_label')" :value="$generatedSecret" />
                <p class="-mt-3 mb-4 text-xs text-muted">
                    {{ __('baobab::admin.webhooks.secret_help') }}
                    <a href="{{ route('admin.webhooks.create') }}" class="text-primary hover:underline">{{ __('baobab::admin.webhooks.secret_regenerate_action') }}</a>
                </p>

                @include('baobab::admin.webhooks.partials.events-field')

                <x-baobab::field.checkbox name="is_active" :label="__('baobab::admin.webhooks.active_label')" :checked="true" />

                <x-baobab::button type="submit" variant="primary">{{ __('baobab::admin.webhooks.save_action') }}</x-baobab::button>
            </x-baobab::form>
        </x-baobab::card>
    </x-baobab::page>
@endsection
