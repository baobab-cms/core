@extends('baobab::layouts.admin')

@section('title', __('baobab::admin.queues.title'))

@section('content')
    <x-baobab::page :title="__('baobab::admin.queues.title')">
        @if ($staleWorker !== null)
            <div role="alert" class="mb-4 rounded-md border border-warning-200 bg-warning-50 px-4 py-3 text-sm text-warning-700">
                {{ __('baobab::admin.queues.stale_worker_warning', ['minutes' => $staleWorker]) }}
            </div>
        @endif

        <x-baobab::card class="mb-6">
            <h2 class="mb-3 text-sm font-medium text-foreground">{{ __('baobab::admin.queues.state_title') }}</h2>

            @if (empty($queueStats))
                <p class="text-sm text-muted">{{ __('baobab::admin.queues.state_empty') }}</p>
            @else
                <ul class="space-y-2 text-sm">
                    @foreach ($queueStats as $stat)
                        <li class="flex items-center justify-between border-b border-border pb-2">
                            <span class="font-medium text-foreground">{{ $stat['queue'] }}</span>
                            <span class="text-muted">
                                {{ __('baobab::admin.queues.state_pending', ['count' => $stat['pending']]) }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-baobab::card>

        <h2 class="mb-3 text-sm font-medium text-foreground">{{ __('baobab::admin.queues.failed_title') }}</h2>

        @if ($failedJobs->isEmpty())
            <div class="rounded-lg border border-border bg-surface">
                <x-baobab::empty-state :message="__('baobab::admin.queues.failed_empty')" />
            </div>
        @else
            <x-baobab::table
                :columns="$columns"
                :rows="$failedJobs"
                row-key="uuid"
                :bulk-actions="[
                    ['route' => route('admin.system.queues.bulk-retry'), 'label' => __('baobab::admin.queues.bulk_retry')],
                    ['route' => route('admin.system.queues.bulk-delete'), 'label' => __('baobab::admin.queues.bulk_delete')],
                ]"
            />
        @endif
    </x-baobab::page>
@endsection
