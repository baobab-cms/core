{{--
    Aucune logique ici : le libellé de source et la variante de pastille sont
    triviaux (deux valeurs fermées), contrairement au statut d'exécution qui
    vit dans ScheduledTaskRunStatus (n° 138).
--}}
<x-baobab::badge :variant="$task->source === 'core' ? 'neutral' : 'info'">
    {{ $task->source === 'core' ? __('baobab::admin.scheduler.source_core') : __('baobab::admin.scheduler.source_module') }}
</x-baobab::badge>

@if ($task->moduleName !== null)
    <p class="mt-1 text-xs text-muted">{{ $task->moduleName }}</p>
@endif

@if ($task->suspended)
    <p class="mt-1 text-xs font-medium text-warning-700">{{ __('baobab::admin.scheduler.suspended_label') }}</p>
@endif
