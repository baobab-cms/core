@extends('baobab::layouts.admin')

@section('title', __('baobab::admin.account.profile.title'))

@section('content')
    <x-baobab::page :title="__('baobab::admin.account.profile.title')">
        @if ($pending)
            <x-baobab::card class="mb-6 border-warning/30">
                <p class="text-sm text-foreground">
                    @if ($pending->stage->value === 'verify')
                        {{ __('baobab::admin.account.profile.pending_verify', ['email' => $user->email]) }}
                    @else
                        {{ __('baobab::admin.account.profile.pending_confirm', ['email' => $pending->new_email]) }}
                    @endif
                </p>
                <p class="mt-1 text-xs text-muted">
                    {{ __('baobab::admin.account.profile.pending_expires', ['date' => $pending->expires_at->format('Y-m-d H:i')]) }}
                </p>
            </x-baobab::card>
        @endif

        <x-baobab::card>
            <p class="mb-4 text-sm text-muted">{{ __('baobab::admin.account.profile.intro') }}</p>

            <x-baobab::form method="PUT" action="{{ route('admin.account.profile.update') }}" class="max-w-sm">
                <x-baobab::field.text
                    name="name"
                    :value="$user->name"
                    autocomplete="name"
                    label="{{ __('baobab::admin.account.profile.name_label') }}"
                />
                <x-baobab::field.text
                    type="email"
                    name="email"
                    :value="$user->email"
                    autocomplete="email"
                    label="{{ __('baobab::admin.account.profile.email_label') }}"
                />
                <x-baobab::button type="submit" variant="primary">
                    {{ __('baobab::admin.account.profile.submit') }}
                </x-baobab::button>
            </x-baobab::form>
        </x-baobab::card>
    </x-baobab::page>
@endsection
