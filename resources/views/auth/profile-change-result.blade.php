@extends('baobab::layouts.guest')

@section('title', __('baobab::admin.auth.profile_change_title'))

@section('content')
    @if ($status === 'invalid')
        <div class="mb-4 rounded-md border border-danger-300 bg-danger-50 p-3 text-sm text-danger-700">
            {{ __('baobab::admin.auth.profile_change_invalid') }}
        </div>
    @elseif ($status === 'email_taken')
        <div class="mb-4 rounded-md border border-danger-300 bg-danger-50 p-3 text-sm text-danger-700">
            {{ $message }}
        </div>
    @elseif ($status === 'awaiting_new_address')
        <p class="mb-4 text-sm text-foreground">{{ __('baobab::admin.auth.profile_change_awaiting') }}</p>
    @else
        <p class="mb-4 text-sm text-foreground">{{ __('baobab::admin.auth.profile_change_applied') }}</p>
    @endif

    <x-baobab::button variant="ghost" size="sm" href="{{ route('login') }}">
        {{ __('baobab::admin.auth.profile_change_login') }}
    </x-baobab::button>
@endsection
