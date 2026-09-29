@extends('baobab::layouts.guest')

@section('title', __('baobab::admin.auth.two_factor_title'))

@section('content')
    {{-- `useRecoveryCode` s'initialise sur l'erreur serveur (M9 point 5, Pass D,
    suivi n° 373) : sans ça, une erreur sur `recovery_code` resterait masquée
    par `x-show="useRecoveryCode"` figé à `false` au premier rendu. --}}
    <div x-data="{ useRecoveryCode: {{ $errors->has('recovery_code') || old('recovery_code') ? 'true' : 'false' }} }">
        <p class="mb-4 text-sm text-muted" x-show="!useRecoveryCode">{{ __('baobab::admin.auth.two_factor_prompt') }}</p>
        <p class="mb-4 text-sm text-muted" x-show="useRecoveryCode" x-cloak>{{ __('baobab::admin.auth.two_factor_recovery_prompt') }}</p>

        <form method="POST" action="{{ route('two-factor.challenge') }}" class="space-y-4">
            @csrf

            <div x-show="!useRecoveryCode">
                <x-baobab::field.text
                    name="code" label="{{ __('baobab::admin.auth.two_factor_code') }}"
                    inputmode="numeric" autocomplete="one-time-code" autofocus
                />
            </div>

            <div x-show="useRecoveryCode" x-cloak>
                <x-baobab::field.text
                    name="recovery_code" label="{{ __('baobab::admin.auth.two_factor_recovery_code') }}"
                    autocomplete="one-time-code"
                />
            </div>

            <x-baobab::button type="submit" class="w-full justify-center">
                {{ __('baobab::admin.auth.submit') }}
            </x-baobab::button>

            <x-baobab::button variant="ghost" size="sm" type="button" class="w-full justify-center" @click="useRecoveryCode = !useRecoveryCode">
                <span x-show="!useRecoveryCode">{{ __('baobab::admin.auth.two_factor_use_recovery_code') }}</span>
                <span x-show="useRecoveryCode" x-cloak>{{ __('baobab::admin.auth.two_factor_use_code') }}</span>
            </x-baobab::button>
        </form>
    </div>
@endsection
