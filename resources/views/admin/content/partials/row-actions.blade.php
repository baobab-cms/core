<div class="flex justify-end gap-3">
    <a href="{{ $editUrl }}" class="text-primary hover:underline">{{ __('baobab::admin.content.edit_action') }}</a>

    <form
        method="POST"
        action="{{ $deleteUrl }}"
        onsubmit="return confirm('{{ __('baobab::admin.content.delete_confirm_title') }}')"
        class="inline"
    >
        @csrf
        @method('DELETE')
        <button type="submit" class="text-danger hover:underline">{{ __('baobab::admin.content.delete_action') }}</button>
    </form>
</div>
