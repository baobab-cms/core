@extends('baobab::layouts.admin')

@section('title', __('baobab::admin.access.title'))

@section('content')
    <x-baobab::page :title="__('baobab::admin.access.title')">
        <x-slot:actions>
            <x-baobab::button variant="secondary" :href="route('admin.access.direct-permissions.index')">
                {{ __('baobab::admin.access.direct_permissions.title') }}
            </x-baobab::button>
        </x-slot:actions>

        <x-baobab::card class="mb-6">
            <x-slot:header>
                <span class="font-medium text-foreground">{{ __('baobab::admin.access.create_role_title') }}</span>
            </x-slot:header>

            <x-baobab::form method="POST" action="{{ route('admin.access.roles.store') }}" class="flex flex-wrap items-end gap-2">
                <x-baobab::field.text name="name" label="{{ __('baobab::admin.access.create_role_name') }}" />
                <x-baobab::field.text name="level" type="number" label="{{ __('baobab::admin.access.create_role_level') }}" />
                <x-baobab::button type="submit" variant="primary">
                    {{ __('baobab::admin.access.create_role_submit') }}
                </x-baobab::button>
            </x-baobab::form>
        </x-baobab::card>

        <div class="overflow-x-auto rounded-lg border border-border">
            <table class="w-full text-left text-sm">
                <thead class="bg-surface-subtle text-xs uppercase text-muted">
                    <tr>
                        <th class="px-3 py-2 font-medium">{{ __('baobab::admin.access.column_permission') }}</th>
                        @foreach ($roles as $role)
                            <th class="px-3 py-2 text-center font-medium">
                                <a href="{{ route('admin.access.roles.show', ['role' => $role]) }}" class="hover:underline">
                                    {{ $role->name }}
                                </a>
                            </th>
                        @endforeach
                    </tr>
                </thead>

                <tbody class="divide-y divide-border">
                    @foreach ($groups as $group)
                        <tr class="bg-surface-subtle">
                            <td colspan="{{ $roles->count() + 1 }}" class="px-3 py-2 font-medium text-foreground">
                                {{ $group['label'] }}

                                @unless ($group['active'])
                                    <x-baobab::badge variant="neutral">
                                        {{ __('baobab::admin.access.inactive_module') }}
                                    </x-baobab::badge>
                                @endunless
                            </td>
                        </tr>

                        @foreach ($group['permissions'] as $permission)
                            <tr>
                                <td class="px-3 py-2 text-foreground">{{ $permission['label'] }}</td>

                                @foreach ($roles as $role)
                                    <td class="px-3 py-2 text-center">
                                        @if ($role->name === 'super-admin')
                                            <input
                                                type="checkbox"
                                                checked
                                                disabled
                                                aria-label="{{ __('baobab::admin.access.always_granted') }}"
                                            >
                                        @else
                                            <form method="POST" action="{{ route('admin.access.toggle', ['role' => $role, 'permission' => $permission['name']]) }}">
                                                @csrf
                                                <input
                                                    type="checkbox"
                                                    x-on:change="$el.form.requestSubmit()"
                                                    @checked($grants[$role->id][$permission['name']] ?? false)
                                                    @disabled(! $group['active'])
                                                    aria-label="{{ $permission['label'] }} — {{ $role->name }}"
                                                >
                                            </form>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-baobab::page>
@endsection
