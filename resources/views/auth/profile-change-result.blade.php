@extends('baobab::layouts.guest')

@section('title', __('baobab::admin.auth.profile_change_title'))

@section('content')
    @if ($status === 'invalid')
        <div class="mb-4 rounded-md border border-red-300 bg-red-50 p-3 text-sm text-red-700">
            {{ __('baobab::admin.auth.profile_change_invalid') }}
        </div>
    @elseif ($status === 'email_taken')
        <div class="mb-4 rounded-md border border-red-300 bg-red-50 p-3 text-sm text-red-700">
            {{ $message }}
        </div>
    @elseif ($status === 'awaiting_new_address')
        <p class="mb-4 text-sm text-foreground">{{ __('baobab::admin.auth.profile_change_awaiting') }}</p>
    @else
        <p class="mb-4 text-sm text-foreground">{{ __('baobab::admin.auth.profile_change_applied') }}</p>
    @endif

    <a href="{{ route('login') }}" class="text-sm text-primary hover:underline">{{ __('baobab::admin.auth.profile_change_login') }}</a>
@endsection
