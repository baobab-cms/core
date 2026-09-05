<div class="flex items-center gap-2">
    <a href="{{ route('admin.forms.edit', ['form' => $form->id]) }}" class="text-primary hover:underline">
        {{ __('baobab::admin.forms.edit_action') }}
    </a>

    <a href="{{ route('admin.forms.export', ['form' => $form->id]) }}" class="text-primary hover:underline">
        {{ __('baobab::admin.forms.export_action') }}
    </a>

    <form
        method="POST"
        action="{{ route('admin.forms.destroy', ['form' => $form->id]) }}"
        onsubmit="return confirm('{{ __('baobab::admin.forms.delete_confirm_title') }}')"
    >
        @csrf
        @method('DELETE')
        <button type="submit" class="text-danger hover:underline">{{ __('baobab::admin.forms.delete_action') }}</button>
    </form>
</div>
