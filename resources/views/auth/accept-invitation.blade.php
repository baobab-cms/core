@extends('baobab::layouts.guest')

@section('title', __('baobab::admin.auth.invitation_title'))

@section('content')
    @if ($errors->any())
        <div class="mb-4 rounded-md border border-red-300 bg-red-50 p-3 text-sm text-red-700">
            {{ $errors->first() }}
        </div>
    @endif

    <p class="mb-4 text-sm text-muted">{{ __('baobab::admin.auth.invitation_intro') }}</p>

    <form method="POST" action="{{ route('invitation.store') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <div>
            <label for="email" class="block text-sm text-foreground">{{ __('baobab::admin.auth.email') }}</label>
            <input
                id="email" name="email" type="email" value="{{ old('email', $email) }}" required autocomplete="email"
                class="mt-1 w-full rounded-md border border-border px-3 py-2 text-sm"
            >
        </div>

        <div>
            <label for="password" class="block text-sm text-foreground">{{ __('baobab::admin.auth.new_password') }}</label>
            <input
                id="password" name="password" type="password" required autofocus autocomplete="new-password"
                class="mt-1 w-full rounded-md border border-border px-3 py-2 text-sm"
            >
        </div>

        <div>
            <label for="password_confirmation" class="block text-sm text-foreground">{{ __('baobab::admin.auth.password_confirmation') }}</label>
            <input
                id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"
                class="mt-1 w-full rounded-md border border-border px-3 py-2 text-sm"
            >
        </div>

        <button type="submit" class="w-full rounded-md bg-primary px-3 py-2 text-sm font-medium text-white">
            {{ __('baobab::admin.auth.invitation_submit') }}
        </button>
    </form>
@endsection
