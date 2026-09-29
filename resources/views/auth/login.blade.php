@extends('baobab::layouts.login')

@section('title', __('baobab::admin.auth.login_title'))

@section('content')
    @if (session('status'))
        <div class="mb-4 rounded-md border border-success p-3 text-sm text-success" role="status">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf

        <x-baobab::field.text
            type="email" name="email" label="{{ __('baobab::admin.auth.email') }}"
            value="{{ old('email') }}" required autofocus autocomplete="email"
        />

        <x-baobab::field.text
            type="password" name="password" label="{{ __('baobab::admin.auth.password') }}"
            required autocomplete="current-password"
        />

        <x-baobab::field.checkbox name="remember" label="{{ __('baobab::admin.auth.remember') }}" />

        <div class="flex justify-end">
            <x-baobab::button variant="ghost" size="sm" href="{{ route('password.request') }}">
                {{ __('baobab::admin.auth.forgot_password_link') }}
            </x-baobab::button>
        </div>

        <x-baobab::button type="submit" class="w-full justify-center">
            {{ __('baobab::admin.auth.submit') }}
        </x-baobab::button>
    </form>
@endsection
