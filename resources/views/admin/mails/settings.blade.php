@extends('baobab::layouts.admin')

@section('title', __('baobab::admin.mail_settings.title'))

@section('content')
    <x-baobab::page
        :title="__('baobab::admin.mail_settings.title')"
        :breadcrumbs="[[__('baobab::admin.mails.title'), route('admin.mails.index')]]"
    >
        <x-baobab::card>
            <x-baobab::form method="PUT" action="{{ route('admin.mails.settings.update') }}">
                <h2 class="mb-3 font-display text-sm font-medium text-foreground">{{ __('baobab::admin.mail_settings.transport_title') }}</h2>

                <x-baobab::field.text name="host" :label="__('baobab::admin.mail_settings.host')" :value="$setting->credentials['host'] ?? null" />
                <x-baobab::field.text type="number" min="1" max="65535" name="port" :label="__('baobab::admin.mail_settings.port')" :value="$setting->credentials['port'] ?? 587" />
                <x-baobab::field.text name="username" :label="__('baobab::admin.mail_settings.username')" :value="$setting->credentials['username'] ?? null" />
                <x-baobab::field.text type="password" name="password" :label="__('baobab::admin.mail_settings.password')" value="" />
                @if ($hasPassword)
                    <p class="-mt-3 mb-4 text-xs text-muted">{{ __('baobab::admin.mail_settings.password_keep_help') }}</p>
                @endif
                <x-baobab::field.select
                    name="scheme"
                    :label="__('baobab::admin.mail_settings.scheme')"
                    :value="$setting->credentials['scheme'] ?? ''"
                    :options="[
                        '' => __('baobab::admin.mail_settings.scheme_auto'),
                        'smtps' => __('baobab::admin.mail_settings.scheme_smtps'),
                    ]"
                />

                <h2 class="mb-3 mt-6 font-display text-sm font-medium text-foreground">{{ __('baobab::admin.mail_settings.sender_title') }}</h2>

                <x-baobab::field.text name="from_address" :label="__('baobab::admin.mail_settings.from_address')" :value="$setting->from_address" />
                <x-baobab::field.text name="from_name" :label="__('baobab::admin.mail_settings.from_name')" :value="$setting->from_name" />

                <x-baobab::button type="submit" variant="primary">{{ __('baobab::admin.mail_settings.save_action') }}</x-baobab::button>
            </x-baobab::form>
        </x-baobab::card>

        <x-baobab::card class="mt-6">
            <h2 class="mb-3 font-display text-sm font-medium text-foreground">{{ __('baobab::admin.mails.test_legend') }}</h2>
            <p class="mb-4 text-xs text-muted">{{ __('baobab::admin.mail_settings.test_hint') }}</p>

            <x-baobab::form method="POST" action="{{ route('admin.mails.settings.test') }}">
                <x-baobab::field.text type="email" name="recipient" :label="__('baobab::admin.mails.test_recipient')" :value="auth('baobab')->user()?->email" />
                <x-baobab::button type="submit" variant="secondary">{{ __('baobab::admin.mails.test_send') }}</x-baobab::button>
            </x-baobab::form>
        </x-baobab::card>
    </x-baobab::page>
@endsection
