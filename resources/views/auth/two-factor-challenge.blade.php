@extends('baobab::layouts.guest')

@section('title', __('baobab::admin.auth.two_factor_title'))

@section('content')
    @if ($errors->any())
        <div class="mb-4 rounded-md border border-red-300 bg-red-50 p-3 text-sm text-red-700">
            {{ $errors->first() }}
        </div>
    @endif

    <p class="mb-4 text-sm text-muted">{{ __('baobab::admin.auth.two_factor_prompt') }}</p>

    <form method="POST" action="{{ route('two-factor.challenge') }}" class="space-y-4">
        @csrf

        <div>
            <label for="code" class="block text-sm text-foreground">{{ __('baobab::admin.auth.two_factor_code') }}</label>
            <input
                id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" required autofocus
                class="mt-1 w-full rounded-md border border-border px-3 py-2 text-sm"
            >
        </div>

        <button type="submit" class="w-full rounded-md bg-primary px-3 py-2 text-sm font-medium text-white">
            {{ __('baobab::admin.auth.submit') }}
        </button>
    </form>
@endsection
