@extends('baobab::layouts.guest')

@section('title', __('baobab::admin.auth.login_title'))

@section('content')
    @if ($errors->any())
        <div class="mb-4 rounded-md border border-red-300 bg-red-50 p-3 text-sm text-red-700">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf

        <div>
            <label for="email" class="block text-sm text-foreground">{{ __('baobab::admin.auth.email') }}</label>
            <input
                id="email" name="email" type="email" value="{{ old('email') }}" required autofocus
                class="mt-1 w-full rounded-md border border-border px-3 py-2 text-sm"
            >
        </div>

        <div>
            <label for="password" class="block text-sm text-foreground">{{ __('baobab::admin.auth.password') }}</label>
            <input
                id="password" name="password" type="password" required
                class="mt-1 w-full rounded-md border border-border px-3 py-2 text-sm"
            >
        </div>

        <button type="submit" class="w-full rounded-md bg-primary px-3 py-2 text-sm font-medium text-white">
            {{ __('baobab::admin.auth.submit') }}
        </button>
    </form>
@endsection
