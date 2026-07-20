<div class="flex items-center gap-2">
    <a href="{{ route('admin.webhooks.deliveries.index', ['subscription' => $subscription->id]) }}" class="text-primary hover:underline">
        {{ __('baobab::admin.webhooks.view_deliveries_action') }}
    </a>

    <a href="{{ route('admin.webhooks.edit', ['subscription' => $subscription->id]) }}" class="text-primary hover:underline">
        {{ __('baobab::admin.webhooks.edit_action') }}
    </a>

    <form
        method="POST"
        action="{{ route('admin.webhooks.destroy', ['subscription' => $subscription->id]) }}"
        onsubmit="return confirm('{{ __('baobab::admin.webhooks.delete_confirm_title') }}')"
    >
        @csrf
        @method('DELETE')
        <button type="submit" class="text-danger hover:underline">{{ __('baobab::admin.webhooks.delete_action') }}</button>
    </form>
</div>
