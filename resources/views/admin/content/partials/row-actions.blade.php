<div class="flex justify-end gap-3">
    @if ($trashed)
        <form method="POST" action="{{ $restoreUrl }}" class="inline">
            @csrf
            <button type="submit" class="text-primary hover:underline">{{ __('baobab::admin.content.restore_action') }}</button>
        </form>

        @if ($canPurge)
            <form
                method="POST"
                action="{{ $purgeUrl }}"
                onsubmit="return confirm('{{ __('baobab::admin.content.purge_confirm_title') }}')"
                class="inline"
            >
                @csrf
                @method('DELETE')
                <button type="submit" class="text-danger hover:underline">{{ __('baobab::admin.content.purge_action') }}</button>
            </form>
        @endif
    @else
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
    @endif
</div>
