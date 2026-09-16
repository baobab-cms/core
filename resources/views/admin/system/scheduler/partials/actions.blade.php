@can('baobab.system.scheduler.run')
    <form method="POST" action="{{ route('admin.system.scheduler.run', ['taskKey' => $task->taskKey]) }}" class="inline">
        @csrf
        <button type="submit" class="text-primary hover:underline">
            {{ __('baobab::admin.scheduler.run_now') }}
        </button>
    </form>

    @if ($task->source === 'module')
        @if ($task->suspended)
            <form method="POST" action="{{ route('admin.system.scheduler.resume', ['taskKey' => $task->taskKey]) }}" class="ml-2 inline">
                @csrf
                <button type="submit" class="text-primary hover:underline">
                    {{ __('baobab::admin.scheduler.resume') }}
                </button>
            </form>
        @else
            <form method="POST" action="{{ route('admin.system.scheduler.suspend', ['taskKey' => $task->taskKey]) }}" class="ml-2 inline">
                @csrf
                <button type="submit" class="text-danger-700 hover:underline">
                    {{ __('baobab::admin.scheduler.suspend') }}
                </button>
            </form>
        @endif
    @endif
@endcan
