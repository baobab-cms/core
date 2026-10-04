<div class="flex items-center gap-2">
    <a href="{{ route('admin.webhooks.deliveries.index', ['subscription' => $subscription->id]) }}" class="text-primary hover:underline">
        {{ __('baobab::admin.webhooks.view_deliveries_action') }}
    </a>

    <a href="{{ route('admin.webhooks.edit', ['subscription' => $subscription->id]) }}" class="text-primary hover:underline">
        {{ __('baobab::admin.webhooks.edit_action') }}
    </a>

    <x-baobab::delete-action
        name="delete-webhook-{{ $subscription->id }}"
        :action="route('admin.webhooks.destroy', ['subscription' => $subscription->id])"
        :title="__('baobab::admin.components.delete.title', ['label' => $subscription->url])"
        :description="__('baobab::admin.components.delete.webhook', ['url' => $subscription->url])"
        :label="__('baobab::admin.webhooks.delete_action')"
    />
</div>
