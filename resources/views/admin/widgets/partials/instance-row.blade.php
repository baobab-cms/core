<div class="flex items-center justify-between gap-3 rounded-md border border-border p-3">
    <div class="text-sm text-foreground">
        {{ $widgetLabels[$instance->widget_key] ?? $instance->widget_key }}
    </div>

    <div class="flex items-center gap-2">
        @if ($reorderable)
            <form method="POST" action="{{ route('admin.widgets.move-up', ['instance' => $instance->id]) }}">
                @csrf
                <button type="submit" class="text-muted hover:text-foreground" title="{{ __('baobab::admin.widgets.move_up_action') }}">&uarr;</button>
            </form>
            <form method="POST" action="{{ route('admin.widgets.move-down', ['instance' => $instance->id]) }}">
                @csrf
                <button type="submit" class="text-muted hover:text-foreground" title="{{ __('baobab::admin.widgets.move_down_action') }}">&darr;</button>
            </form>
        @endif

        <a href="{{ route('admin.widgets.edit', ['instance' => $instance->id]) }}" class="text-primary hover:underline">
            {{ __('baobab::admin.widgets.edit_action') }}
        </a>

        <x-baobab::delete-action
            name="delete-widget-{{ $instance->id }}"
            :action="route('admin.widgets.destroy', ['instance' => $instance->id])"
            :title="__('baobab::admin.components.delete.title', ['label' => $widgetLabels[$instance->widget_key] ?? $instance->widget_key])"
            :description="__('baobab::admin.components.delete.widget', ['zone' => $instance->zone_key])"
            :label="__('baobab::admin.widgets.delete_action')"
        />
    </div>
</div>
