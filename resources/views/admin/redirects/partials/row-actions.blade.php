<div class="flex items-center gap-2">
    <a href="{{ route('admin.redirects.edit', ['redirect' => $redirect->id]) }}" class="text-primary hover:underline">
        {{ __('baobab::admin.redirects.edit_action') }}
    </a>

    <x-baobab::delete-action
        name="delete-redirect-{{ $redirect->id }}"
        :action="route('admin.redirects.destroy', ['redirect' => $redirect->id])"
        :title="__('baobab::admin.components.delete.title', ['label' => $redirect->source])"
        :description="__('baobab::admin.components.delete.redirect', ['source' => $redirect->source])"
        :label="__('baobab::admin.redirects.delete_action')"
    />
</div>
