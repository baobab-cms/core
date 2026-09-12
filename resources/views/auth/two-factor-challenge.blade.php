@extends('baobab::layouts.guest')

@section('title', __('baobab::admin.auth.two_factor_title'))

@section('content')
    @if ($errors->any())
        <div class="mb-4 rounded-md border border-red-300 bg-red-50 p-3 text-sm text-red-700">
            {{ $errors->first() }}
        </div>
    @endif

    <div x-data="{ useRecoveryCode: false }">
        <p class="mb-4 text-sm text-muted" x-show="!useRecoveryCode">{{ __('baobab::admin.auth.two_factor_prompt') }}</p>
        <p class="mb-4 text-sm text-muted" x-show="useRecoveryCode" x-cloak>{{ __('baobab::admin.auth.two_factor_recovery_prompt') }}</p>

        <form method="POST" action="{{ route('two-factor.challenge') }}" class="space-y-4">
            @csrf

            <div x-show="!useRecoveryCode">
                <label for="code" class="block text-sm text-foreground">{{ __('baobab::admin.auth.two_factor_code') }}</label>
                <input
                    id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" autofocus
                    class="mt-1 w-full rounded-md border border-border px-3 py-2 text-sm"
                >
            </div>

            <div x-show="useRecoveryCode" x-cloak>
                <label for="recovery_code" class="block text-sm text-foreground">{{ __('baobab::admin.auth.two_factor_recovery_code') }}</label>
                <input
                    id="recovery_code" name="recovery_code" type="text" autocomplete="one-time-code"
                    class="mt-1 w-full rounded-md border border-border px-3 py-2 text-sm"
                >
            </div>

            <button type="submit" class="w-full rounded-md bg-primary px-3 py-2 text-sm font-medium text-white">
                {{ __('baobab::admin.auth.submit') }}
            </button>

            <button type="button" class="text-sm text-muted underline" @click="useRecoveryCode = !useRecoveryCode">
                <span x-show="!useRecoveryCode">{{ __('baobab::admin.auth.two_factor_use_recovery_code') }}</span>
                <span x-show="useRecoveryCode" x-cloak>{{ __('baobab::admin.auth.two_factor_use_code') }}</span>
            </button>
        </form>
    </div>
@endsection
