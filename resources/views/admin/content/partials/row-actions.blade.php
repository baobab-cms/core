{{--
    Suppression et purge sur `<x-baobab::confirm>` plutôt que le `confirm()`
    natif du navigateur (suivi n° 379 décision 1, §9 : « le titre dit le
    nombre et l'objet », jamais « Êtes-vous sûr »). `$entryLabel` vient du
    champ titre du Content Type (repli sur `#id` s'il est vide).
--}}
<div class="flex justify-end gap-3">
    @if ($trashed)
        <form method="POST" action="{{ $restoreUrl }}" class="inline">
            @csrf
            <button type="submit" class="text-primary hover:underline">{{ __('baobab::admin.content.restore_action') }}</button>
        </form>

        @if ($canPurge)
            <button type="button" class="text-danger hover:underline" x-on:click="$dispatch('open-modal', 'purge-entry-{{ $entryId }}')">
                {{ __('baobab::admin.content.purge_action') }}
            </button>

            <x-baobab::confirm name="purge-entry-{{ $entryId }}" :title="__('baobab::admin.content.purge_confirm_title', ['label' => $entryLabel])">
                <x-slot:description>{{ __('baobab::admin.content.purge_confirm_description') }}</x-slot:description>

                <form method="POST" action="{{ $purgeUrl }}">
                    @csrf
                    @method('DELETE')
                    <x-baobab::button type="submit" variant="danger" class="w-full justify-center">
                        {{ __('baobab::admin.content.purge_action') }}
                    </x-baobab::button>
                </form>
            </x-baobab::confirm>
        @endif
    @else
        <a href="{{ $editUrl }}" class="text-primary hover:underline">{{ __('baobab::admin.content.edit_action') }}</a>

        <button type="button" class="text-danger hover:underline" x-on:click="$dispatch('open-modal', 'delete-entry-{{ $entryId }}')">
            {{ __('baobab::admin.content.delete_action') }}
        </button>

        <x-baobab::confirm name="delete-entry-{{ $entryId }}" :title="__('baobab::admin.content.delete_confirm_title', ['label' => $entryLabel])">
            <x-slot:description>{{ __('baobab::admin.content.delete_confirm_description') }}</x-slot:description>

            <form method="POST" action="{{ $deleteUrl }}">
                @csrf
                @method('DELETE')
                <x-baobab::button type="submit" variant="danger" class="w-full justify-center">
                    {{ __('baobab::admin.content.delete_action') }}
                </x-baobab::button>
            </form>
        </x-baobab::confirm>
    @endif
</div>
