@extends('baobab::layouts.guest')

@section('title', __('baobab::admin.auth.reset_password_title'))

@section('content')
    <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <x-baobab::field.text
            type="email" name="email" label="{{ __('baobab::admin.auth.email') }}"
            value="{{ old('email', $email) }}" required autocomplete="email"
        />

        <x-baobab::field.text
            type="password" name="password" label="{{ __('baobab::admin.auth.new_password') }}"
            required autofocus autocomplete="new-password"
        />

        <x-baobab::field.text
            type="password" name="password_confirmation" label="{{ __('baobab::admin.auth.password_confirmation') }}"
            required autocomplete="new-password"
        />

        <x-baobab::button type="submit" class="w-full justify-center">
            {{ __('baobab::admin.auth.reset_password_submit') }}
        </x-baobab::button>
    </form>

    <p class="mt-4 text-center">
        <x-baobab::button variant="ghost" size="sm" href="{{ route('password.request') }}">
            {{ __('baobab::admin.auth.forgot_password_title') }}
        </x-baobab::button>
    </p>
@endsection
