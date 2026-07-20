<div class="mb-4">
    <p class="mb-1 block text-sm font-medium text-foreground">{{ __('baobab::admin.webhooks.events_label') }}</p>

    @foreach ($events as $event)
        <label class="mb-1 flex items-center gap-2 text-sm text-foreground">
            <input type="checkbox" name="events[]" value="{{ $event['name'] }}" @checked($event['checked']) class="rounded border-border">
            {{ $event['name'] }}
        </label>
    @endforeach

    @error('events')
        <p class="mt-1 text-xs text-danger">{{ $message }}</p>
    @enderror
</div>
