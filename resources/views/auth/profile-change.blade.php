@extends('baobab::layouts.guest')

@section('title', __('baobab::admin.auth.profile_change_title'))

@section('content')
    @if ($pending->stage->value === 'verify')
        <p class="mb-3 text-sm text-muted">{{ __('baobab::admin.auth.profile_change_intro_verify') }}</p>
    @else
        <p class="mb-3 text-sm text-muted">{{ __('baobab::admin.auth.profile_change_intro_confirm', ['name' => $pending->user->name]) }}</p>
    @endif

    <ul class="mb-4 space-y-1 text-sm text-foreground">
        @if ($pending->new_name !== null)
            <li>{{ __('baobab::admin.auth.profile_change_new_name', ['name' => $pending->new_name]) }}</li>
        @endif
        @if ($pending->new_email !== null)
            <li>{{ __('baobab::admin.auth.profile_change_new_email', ['email' => $pending->new_email]) }}</li>
        @endif
    </ul>

    <form method="POST" action="{{ route('profile-change.confirm') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <button type="submit" class="w-full rounded-md bg-primary px-3 py-2 text-sm font-medium text-white">
            {{ __('baobab::admin.auth.profile_change_submit') }}
        </button>
    </form>
@endsection
