<form method="POST" action="{{ route('admin.webhooks.deliveries.redeliver', ['delivery' => $delivery->id]) }}">
    @csrf
    <button type="submit" class="text-primary hover:underline">{{ __('baobab::admin.webhooks.redeliver_action') }}</button>
</form>
