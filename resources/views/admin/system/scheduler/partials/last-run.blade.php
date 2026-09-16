@if ($task->lastRun === null)
    <span class="text-muted">{{ __('baobab::admin.scheduler.never_run') }}</span>
@else
    <x-baobab::badge :variant="$task->lastRun->status->badgeVariant()">{{ $task->lastRun->status->label() }}</x-baobab::badge>

    <p class="mt-1 text-xs text-muted">
        {{ $task->lastRun->started_at->format('Y-m-d H:i') }}
        @if ($task->lastRun->finished_at !== null)
            &middot; {{ $task->lastRun->started_at->diffInSeconds($task->lastRun->finished_at) }}s
        @endif
    </p>

    @if ($task->lastRun->error !== null)
        <p class="mt-1 max-w-xs truncate text-xs text-muted" title="{{ $task->lastRun->error }}">{{ $task->lastRun->error }}</p>
    @endif
@endif
