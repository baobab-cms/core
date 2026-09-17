@can('baobab.system.backups.download')
    <a href="{{ route('admin.system.backups.download', ['token' => $backup['token']]) }}" class="text-primary hover:underline">
        {{ __('baobab::admin.backups.download') }}
    </a>
@endcan

@can('baobab.system.backups.create')
    <x-baobab::button
        type="button"
        variant="danger"
        class="ml-2"
        x-on:click="$dispatch('open-modal', 'delete-backup-{{ $backup['token'] }}')"
    >
        {{ __('baobab::admin.backups.delete') }}
    </x-baobab::button>

    <x-baobab::confirm
        name="delete-backup-{{ $backup['token'] }}"
        :title="__('baobab::admin.backups.delete')"
    >
        <x-slot:description>
            {{ __('baobab::admin.backups.delete_confirm') }}
        </x-slot:description>

        <x-baobab::form method="DELETE" action="{{ route('admin.system.backups.delete', ['token' => $backup['token']]) }}">
            <x-baobab::button type="submit" variant="danger">
                {{ __('baobab::admin.backups.delete') }}
            </x-baobab::button>
        </x-baobab::form>
    </x-baobab::confirm>
@endcan
