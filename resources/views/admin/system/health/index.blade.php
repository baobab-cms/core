@extends('baobab::layouts.admin')

@use('Baobab\System\HealthCheckStatusPresenter')

@section('title', __('baobab::admin.health.title'))

@section('content')
    <x-baobab::page :title="__('baobab::admin.health.title')">
        <div class="mb-4 flex items-center justify-between">
            <p class="text-sm text-muted">
                @if ($finishedAt !== null)
                    {{ __('baobab::admin.health.last_checked_at') }} {{ $finishedAt->format('Y-m-d H:i:s') }}
                @else
                    {{ __('baobab::admin.health.never_checked') }}
                @endif
            </p>

            <x-baobab::form method="POST" action="{{ route('admin.system.health.refresh') }}">
                <x-baobab::button type="submit" variant="secondary">
                    {{ __('baobab::admin.health.refresh') }}
                </x-baobab::button>
            </x-baobab::form>
        </div>

        @if ($results === null)
            <div class="rounded-lg border border-border bg-surface">
                <x-baobab::empty-state :message="__('baobab::admin.health.never_checked')" />
            </div>
        @else
            <x-baobab::card>
                <ul class="divide-y divide-border">
                    @foreach ($results->storedCheckResults as $result)
                        <li class="flex items-center justify-between py-3">
                            <div>
                                <p class="text-sm font-medium text-foreground">{{ $result->label }}</p>
                                @if ($result->notificationMessage !== '')
                                    <p class="mt-1 text-xs text-muted">{{ $result->notificationMessage }}</p>
                                @endif
                            </div>

                            <div class="flex items-center gap-2">
                                @if ($result->shortSummary !== '')
                                    <span class="text-xs text-muted">{{ $result->shortSummary }}</span>
                                @endif

                                <x-baobab::badge :variant="HealthCheckStatusPresenter::badgeVariant($result->status)">
                                    {{ HealthCheckStatusPresenter::label($result->status) }}
                                </x-baobab::badge>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </x-baobab::card>
        @endif
    </x-baobab::page>
@endsection
