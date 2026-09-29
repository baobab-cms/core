@extends('baobab::layouts.guest')

@section('title', __('baobab::admin.auth.forgot_password_title'))

@section('content')
    @if (session('status'))
        <div class="mb-4 rounded-md border border-success p-3 text-sm text-success" role="status">
            {{ session('status') }}
        </div>
    @endif

    <p class="mb-4 text-sm text-muted">{{ __('baobab::admin.auth.forgot_password_intro') }}</p>

    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
        @csrf

        <x-baobab::field.text
            type="email" name="email" label="{{ __('baobab::admin.auth.email') }}"
            value="{{ old('email') }}" required autofocus autocomplete="email"
        />

        <x-baobab::button type="submit" class="w-full justify-center">
            {{ __('baobab::admin.auth.forgot_password_submit') }}
        </x-baobab::button>
    </form>

    <p class="mt-4 text-center">
        <x-baobab::button variant="ghost" size="sm" href="{{ route('login') }}">
            {{ __('baobab::admin.auth.back_to_login') }}
        </x-baobab::button>
    </p>
@endsection
