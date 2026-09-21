@extends('baobab::layouts.admin')

@use('Baobab\Privacy\PrivacyRequestStatus')

@section('title', __('baobab::admin.privacy_requests.detail_title'))

@section('content')
    <x-baobab::page :title="__('baobab::admin.privacy_requests.detail_title')">
        <x-slot:actions>
            <x-baobab::button :href="route('admin.privacy.requests.index')" variant="secondary">
                {{ __('baobab::admin.privacy_requests.back') }}
            </x-baobab::button>
        </x-slot:actions>

        <x-baobab::card class="mb-6">
            <dl class="grid gap-3 text-sm sm:grid-cols-[12rem_1fr]">
                <dt class="text-muted">{{ __('baobab::admin.privacy_requests.column_subject') }}</dt>
                <dd class="text-foreground">{{ $privacyRequest->subjectLabel() }}</dd>

                <dt class="text-muted">{{ __('baobab::admin.privacy_requests.column_status') }}</dt>
                <dd>@include('baobab::admin.privacy.requests.partials.status-badge', ['request' => $privacyRequest])</dd>

                <dt class="text-muted">{{ __('baobab::admin.privacy_requests.column_date') }}</dt>
                <dd class="text-foreground">{{ $privacyRequest->created_at->format('Y-m-d H:i') }}</dd>

                <dt class="text-muted">{{ __('baobab::admin.privacy_requests.requested_by') }}</dt>
                <dd class="text-foreground">{{ $privacyRequest->requester?->name ?? '—' }}</dd>

                <dt class="text-muted">{{ __('baobab::admin.privacy_requests.column_origin') }}</dt>
                <dd class="text-foreground">{{ $privacyRequest->originLabel() }}</dd>

                <dt class="text-muted">{{ __('baobab::admin.privacy_requests.column_type') }}</dt>
                <dd class="text-foreground">{{ __('baobab::admin.privacy_requests.type_'.$privacyRequest->type->value) }}</dd>

                @if ($privacyRequest->scheduled_for)
                    <dt class="text-muted">{{ __('baobab::admin.privacy_requests.column_scheduled') }}</dt>
                    <dd class="text-foreground">{{ $privacyRequest->scheduled_for->format('Y-m-d H:i') }}</dd>
                @endif

                <dt class="text-muted">{{ __('baobab::admin.privacy_requests.column_expires') }}</dt>
                <dd class="text-foreground">{{ $privacyRequest->expires_at?->format('Y-m-d H:i') ?? '—' }}</dd>

                @if ($privacyRequest->error_message)
                    <dt class="text-muted">{{ __('baobab::admin.privacy_requests.error') }}</dt>
                    <dd class="text-danger">{{ $privacyRequest->error_message }}</dd>
                @endif
            </dl>
        </x-baobab::card>

        @if ($privacyRequest->isCancellable())
            <x-baobab::card class="mb-6">
                <h2 class="mb-2 text-sm font-medium text-foreground">{{ __('baobab::admin.privacy_requests.cancel_title') }}</h2>
                <p class="mb-3 text-sm text-muted">{{ __('baobab::admin.privacy_requests.cancel_help', ['date' => $privacyRequest->scheduled_for?->format('Y-m-d H:i')]) }}</p>
                <x-baobab::form method="POST" action="{{ route('admin.privacy.requests.cancel', ['privacyRequest' => $privacyRequest->uuid]) }}">
                    <x-baobab::button type="submit" variant="secondary">
                        {{ __('baobab::admin.privacy_requests.cancel') }}
                    </x-baobab::button>
                </x-baobab::form>
            </x-baobab::card>
        @endif

        @if ($downloadUrl)
            <x-baobab::card class="mb-6">
                <h2 class="mb-2 text-sm font-medium text-foreground">{{ __('baobab::admin.privacy_requests.archive_title') }}</h2>
                <p class="mb-3 text-sm text-muted">{{ __('baobab::admin.privacy_requests.archive_help') }}</p>
                <x-baobab::button :href="$downloadUrl" variant="secondary">
                    {{ __('baobab::admin.privacy_requests.download') }}
                </x-baobab::button>
            </x-baobab::card>
        @endif

        @if ($revealedPassword)
            <div role="alert" class="mb-6 rounded-lg border border-warning bg-surface p-4 text-sm text-foreground">
                <p class="font-medium">{{ __('baobab::admin.privacy_requests.password_revealed') }}</p>
                <p class="mt-2 break-all font-mono text-base">{{ $revealedPassword }}</p>
                <p class="mt-2 text-muted">{{ __('baobab::admin.privacy_requests.password_once') }}</p>
            </div>
        @elseif ($privacyRequest->hasPendingPassword())
            <x-baobab::card class="mb-6">
                <h2 class="mb-2 text-sm font-medium text-foreground">{{ __('baobab::admin.privacy_requests.password_title') }}</h2>
                <p class="mb-3 text-sm text-muted">{{ __('baobab::admin.privacy_requests.password_help') }}</p>
                <x-baobab::form method="POST" action="{{ route('admin.privacy.requests.reveal-password', ['privacyRequest' => $privacyRequest->uuid]) }}">
                    <x-baobab::button type="submit" variant="primary">
                        {{ __('baobab::admin.privacy_requests.reveal') }}
                    </x-baobab::button>
                </x-baobab::form>
            </x-baobab::card>
        @elseif ($privacyRequest->status === PrivacyRequestStatus::Completed)
            <p class="text-sm text-muted">{{ __('baobab::admin.privacy_requests.password_gone') }}</p>
        @endif
    </x-baobab::page>
@endsection
