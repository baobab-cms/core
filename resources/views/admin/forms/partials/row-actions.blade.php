<div class="flex items-center gap-2">
    <a href="{{ route('admin.forms.edit', ['form' => $form->id]) }}" class="text-primary hover:underline">
        {{ __('baobab::admin.forms.edit_action') }}
    </a>

    <a href="{{ route('admin.forms.submissions.index', ['form' => $form->id]) }}" class="text-primary hover:underline">
        {{ __('baobab::admin.forms.submissions_action') }}
    </a>

    <a href="{{ route('admin.forms.export', ['form' => $form->id]) }}" class="text-primary hover:underline">
        {{ __('baobab::admin.forms.export_action') }}
    </a>

    <x-baobab::delete-action
        name="delete-form-{{ $form->id }}"
        :action="route('admin.forms.destroy', ['form' => $form->id])"
        :title="__('baobab::admin.components.delete.title', ['label' => $form->title])"
        :description="__('baobab::admin.components.delete.form')"
        :label="__('baobab::admin.forms.delete_action')"
    />
</div>
