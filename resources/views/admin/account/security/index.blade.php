@extends('baobab::layouts.admin')

@section('title', __('baobab::admin.account.security.title'))

@section('content')
    <x-baobab::page :title="__('baobab::admin.account.security.title')">
        @if ($recoveryCodes)
            <x-baobab::card class="mb-6 border-warning/30">
                <x-slot:header>
                    <span class="font-medium text-foreground">{{ __('baobab::admin.account.security.recovery_codes_title') }}</span>
                </x-slot:header>

                <p class="mb-3 text-sm text-muted">{{ __('baobab::admin.account.security.recovery_codes_warning') }}</p>

                <ul class="grid grid-cols-2 gap-2 font-mono text-sm text-foreground sm:grid-cols-4">
                    @foreach ($recoveryCodes as $code)
                        <li class="rounded-md bg-surface-subtle px-2 py-1 text-center">{{ $code }}</li>
                    @endforeach
                </ul>
            </x-baobab::card>
        @endif

        @if (! $user->hasTwoFactorEnabled() && $user->two_factor_secret === null)
            <x-baobab::card>
                <p class="mb-4 text-sm text-muted">{{ __('baobab::admin.account.security.not_enabled_intro') }}</p>

                <x-baobab::form method="POST" action="{{ route('admin.account.security.enable') }}">
                    <x-baobab::button type="submit" variant="primary">
                        {{ __('baobab::admin.account.security.enable_action') }}
                    </x-baobab::button>
                </x-baobab::form>
            </x-baobab::card>
        @elseif (! $user->hasTwoFactorEnabled())
            <x-baobab::card>
                <p class="mb-4 text-sm text-muted">{{ __('baobab::admin.account.security.pending_intro') }}</p>

                <div class="mb-4">{!! $qrCodeSvg !!}</div>

                <p class="mb-4 text-xs text-muted">
                    {{ __('baobab::admin.account.security.secret_fallback_label') }}
                    <span class="font-mono text-foreground">{{ $user->two_factor_secret }}</span>
                </p>

                <x-baobab::form method="POST" action="{{ route('admin.account.security.confirm') }}" class="max-w-xs">
                    <x-baobab::field.text name="code" label="{{ __('baobab::admin.account.security.confirm_code_label') }}" />
                    <x-baobab::button type="submit" variant="primary">
                        {{ __('baobab::admin.account.security.confirm_action') }}
                    </x-baobab::button>
                </x-baobab::form>
            </x-baobab::card>
        @else
            <x-baobab::card class="mb-6">
                <p class="text-sm text-foreground">
                    {{ __('baobab::admin.account.security.enabled_since', ['date' => $user->two_factor_confirmed_at?->translatedFormat('d/m/Y')]) }}
                </p>
            </x-baobab::card>

            <div class="flex flex-wrap gap-2">
                <x-baobab::button type="button" variant="secondary" x-on:click="$dispatch('open-modal', 'regenerate-recovery-codes')">
                    {{ __('baobab::admin.account.security.regenerate_recovery_codes_action') }}
                </x-baobab::button>

                <x-baobab::button type="button" variant="danger" x-on:click="$dispatch('open-modal', 'disable-two-factor')">
                    {{ __('baobab::admin.account.security.disable_action') }}
                </x-baobab::button>
            </div>

            <x-baobab::confirm
                name="regenerate-recovery-codes"
                :title="__('baobab::admin.account.security.regenerate_recovery_codes_confirm_title')"
            >
                <x-slot:description>
                    {{ __('baobab::admin.account.security.regenerate_recovery_codes_confirm_description') }}
                </x-slot:description>

                <x-baobab::form method="POST" action="{{ route('admin.account.security.recovery-codes.regenerate') }}">
                    <x-baobab::button type="submit" variant="danger">
                        {{ __('baobab::admin.account.security.regenerate_recovery_codes_action') }}
                    </x-baobab::button>
                </x-baobab::form>
            </x-baobab::confirm>

            <x-baobab::confirm
                name="disable-two-factor"
                :title="__('baobab::admin.account.security.disable_confirm_title')"
                :open="$errors->has('current_password')"
            >
                <x-slot:description>
                    {{ __('baobab::admin.account.security.disable_confirm_description') }}
                </x-slot:description>

                <x-baobab::form method="POST" action="{{ route('admin.account.security.disable') }}">
                    <x-baobab::field.text
                        type="password"
                        name="current_password"
                        label="{{ __('baobab::admin.account.security.current_password_label') }}"
                    />
                    <x-baobab::button type="submit" variant="danger">
                        {{ __('baobab::admin.account.security.disable_action') }}
                    </x-baobab::button>
                </x-baobab::form>
            </x-baobab::confirm>
        @endif
    </x-baobab::page>
@endsection
