<div class="flex flex-wrap items-center gap-2">
    <a href="{{ route('admin.forms.submissions.show', ['form' => $form->id, 'submission' => $submission->id]) }}" class="text-primary hover:underline">
        {{ __('baobab::admin.form_submissions.view_action') }}
    </a>

    <form method="POST" action="{{ route('admin.forms.submissions.mark-status', ['form' => $form->id, 'submission' => $submission->id]) }}">
        @csrf
        <select name="status" x-data x-on:change="$el.form.submit()" class="rounded-md border border-border bg-surface px-1 py-0.5 text-xs text-foreground">
            @foreach ($statusOptions as $value => $label)
                @continue($value === '')
                <option value="{{ $value }}" @selected($submission->status->value === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </form>

    <form
        method="POST"
        action="{{ route('admin.forms.submissions.destroy', ['form' => $form->id, 'submission' => $submission->id]) }}"
        onsubmit="return confirm('{{ __('baobab::admin.form_submissions.delete_confirm_title') }}')"
    >
        @csrf
        @method('DELETE')
        <button type="submit" class="text-danger hover:underline">{{ __('baobab::admin.form_submissions.delete_action') }}</button>
    </form>
</div>
