@extends('baobab::layouts.admin')

@section('title', __('baobab::admin.webhooks.edit_title'))

@section('content')
    <x-baobab::page :title="__('baobab::admin.webhooks.edit_title')">
        <x-baobab::card>
            <x-baobab::form method="PUT" action="{{ route('admin.webhooks.update', ['subscription' => $subscription->id]) }}">
                <x-baobab::field.text name="url" :label="__('baobab::admin.webhooks.url_label')" :value="$subscription->url" />
                <x-baobab::field.text name="secret" :label="__('baobab::admin.webhooks.secret_label')" />
                <p class="-mt-3 mb-4 text-xs text-muted">{{ __('baobab::admin.webhooks.secret_keep_help') }}</p>

                @include('baobab::admin.webhooks.partials.events-field')

                <x-baobab::field.checkbox name="is_active" :label="__('baobab::admin.webhooks.active_label')" :checked="$subscription->is_active" />

                <x-baobab::button type="submit" variant="primary">{{ __('baobab::admin.webhooks.save_action') }}</x-baobab::button>
            </x-baobab::form>

            <p class="mt-4 text-sm">
                <a href="{{ route('admin.webhooks.deliveries.index', ['subscription' => $subscription->id]) }}" class="text-primary hover:underline">
                    {{ __('baobab::admin.webhooks.view_deliveries_action') }}
                </a>
            </p>
        </x-baobab::card>
    </x-baobab::page>
@endsection
