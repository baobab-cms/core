@extends('baobab::layouts.guest')

@section('title', __('baobab::admin.auth.forgot_password_title'))

@section('content')
    @if (session('status'))
        <div class="mb-4 rounded-md border border-success p-3 text-sm text-success" role="status">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-4 rounded-md border border-red-300 bg-red-50 p-3 text-sm text-red-700">
            {{ $errors->first() }}
        </div>
    @endif

    <p class="mb-4 text-sm text-muted">{{ __('baobab::admin.auth.forgot_password_intro') }}</p>

    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
        @csrf

        <div>
            <label for="email" class="block text-sm text-foreground">{{ __('baobab::admin.auth.email') }}</label>
            <input
                id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="email"
                class="mt-1 w-full rounded-md border border-border px-3 py-2 text-sm"
            >
        </div>

        <button type="submit" class="w-full rounded-md bg-primary px-3 py-2 text-sm font-medium text-white">
            {{ __('baobab::admin.auth.forgot_password_submit') }}
        </button>
    </form>

    <p class="mt-4 text-center text-sm">
        <a href="{{ route('login') }}" class="text-primary underline">{{ __('baobab::admin.auth.back_to_login') }}</a>
    </p>
@endsection
