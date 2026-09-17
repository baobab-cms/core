@can('baobab.system.queues.manage')
    <form method="POST" action="{{ route('admin.system.queues.retry', ['failedJob' => $job->uuid]) }}" class="inline">
        @csrf
        <button type="submit" class="text-primary hover:underline">
            {{ __('baobab::admin.queues.retry') }}
        </button>
    </form>

    <form method="POST" action="{{ route('admin.system.queues.delete', ['failedJob' => $job->uuid]) }}" class="ml-2 inline">
        @csrf
        <button type="submit" class="text-danger-700 hover:underline">
            {{ __('baobab::admin.queues.delete') }}
        </button>
    </form>
@endcan
