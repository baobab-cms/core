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

        <form method="POST" action="{{ route('admin.widgets.destroy', ['instance' => $instance->id]) }}" onsubmit="return confirm('{{ __('baobab::admin.widgets.delete_confirm') }}')">
            @csrf
            @method('DELETE')
            <button type="submit" class="text-danger hover:underline">{{ __('baobab::admin.widgets.delete_action') }}</button>
        </form>
    </div>
</div>
