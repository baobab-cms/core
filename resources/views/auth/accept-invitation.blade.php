@extends('baobab::layouts.guest')

@section('title', __('baobab::admin.auth.invitation_title'))

@section('content')
    <p class="mb-4 text-sm text-muted">{{ __('baobab::admin.auth.invitation_intro') }}</p>

    <form method="POST" action="{{ route('invitation.store') }}" class="space-y-4">
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
            {{ __('baobab::admin.auth.invitation_submit') }}
        </x-baobab::button>
    </form>
@endsection
