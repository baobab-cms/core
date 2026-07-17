<div class="flex items-center gap-2">
    <a href="{{ route('admin.redirects.edit', ['redirect' => $redirect->id]) }}" class="text-primary hover:underline">
        {{ __('baobab::admin.redirects.edit_action') }}
    </a>

    <form
        method="POST"
        action="{{ route('admin.redirects.destroy', ['redirect' => $redirect->id]) }}"
        onsubmit="return confirm('{{ __('baobab::admin.redirects.delete_confirm_title') }}')"
    >
        @csrf
        @method('DELETE')
        <button type="submit" class="text-danger hover:underline">{{ __('baobab::admin.redirects.delete_action') }}</button>
    </form>
</div>
