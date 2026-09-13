@extends('baobab::layouts.admin')

@section('title', $role->name)

@section('content')
    <x-baobab::page
        :title="$role->name"
        :breadcrumbs="[[__('baobab::admin.access.title'), route('admin.access.index')], [$role->name]]"
    >
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <div class="space-y-6">
                <x-baobab::card :header="__('baobab::admin.access.show.level_title')">
                    <p class="text-sm text-muted">
                        {{ __('baobab::admin.access.show.level_description', ['level' => $role->level]) }}
                    </p>
                </x-baobab::card>

                <x-baobab::card :header="__('baobab::admin.access.show.settings_title')">
                    <x-baobab::form method="PATCH" action="{{ route('admin.access.roles.settings.update', ['role' => $role]) }}">
                        <x-baobab::field.checkbox
                            name="requires_two_factor"
                            label="{{ __('baobab::admin.access.show.requires_two_factor_label') }}"
                            :checked="(bool) $role->requires_two_factor"
                        />
                        <p class="-mt-3 mb-4 text-xs text-muted">{{ __('baobab::admin.access.show.requires_two_factor_help') }}</p>

                        <x-baobab::button type="submit" variant="primary">
                            {{ __('baobab::admin.access.show.settings_save_action') }}
                        </x-baobab::button>
                    </x-baobab::form>
                </x-baobab::card>

                <x-baobab::card :header="__('baobab::admin.access.show.users_title')">
                    @forelse ($role->users as $user)
                        <p class="border-b border-border py-2 text-sm text-foreground last:border-0">
                            {{ $user->name }} <span class="text-muted">— {{ $user->email }}</span>
                        </p>
                    @empty
                        <p class="text-sm text-muted">{{ __('baobab::admin.access.show.users_empty') }}</p>
                    @endforelse
                </x-baobab::card>
            </div>

            <x-baobab::card :header="__('baobab::admin.access.show.permissions_title')">
                <p class="mb-3 text-xs text-muted">{{ __('baobab::admin.access.show.permissions_hint') }}</p>

                <div class="divide-y divide-border">
                    @foreach ($groups as $group)
                        <div class="py-2">
                            <p class="font-medium text-foreground">
                                {{ $group['label'] }}

                                @unless ($group['active'])
                                    <x-baobab::badge variant="neutral">
                                        {{ __('baobab::admin.access.inactive_module') }}
                                    </x-baobab::badge>
                                @endunless
                            </p>

                            <ul class="mt-1 space-y-1">
                                @foreach ($group['permissions'] as $permission)
                                    <li class="flex items-center gap-2 text-sm text-muted">
                                        <input
                                            type="checkbox"
                                            disabled
                                            @checked($grants[$permission['name']] ?? false)
                                            aria-label="{{ $permission['label'] }}"
                                        >
                                        {{ $permission['label'] }}
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </div>
            </x-baobab::card>
        </div>
    </x-baobab::page>
@endsection
