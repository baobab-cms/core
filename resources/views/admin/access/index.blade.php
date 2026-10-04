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
                {{-- `mb-4` (suivi n° 381) : aligne le bouton sur les champs voisins, qui
                portent cette marge sur leur conteneur — sans elle, `items-end` décroche
                le bouton 16px plus bas que leur contrôle visible. --}}
                <x-baobab::button type="submit" variant="primary" class="mb-4">
                    {{ __('baobab::admin.access.create_role_submit') }}
                </x-baobab::button>
            </x-baobab::form>
        </x-baobab::card>

        {{-- Sous `md`, la matrice est trop large pour une modification fiable (R3) :
        un avertissement, puis la même matrice rôle par rôle plus bas. --}}
        <p class="mb-3 text-sm text-muted md:hidden">{{ __('baobab::admin.access.mobile_warning') }}</p>

        <div class="hidden overflow-x-auto rounded-lg border border-border md:block">
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

        <div class="space-y-3 md:hidden">
            @foreach ($roles as $role)
                <details class="rounded-lg border border-border">
                    <summary class="flex min-h-11 cursor-pointer items-center px-3 font-medium text-foreground">
                        {{ $role->name }}
                    </summary>

                    @if ($role->name === 'super-admin')
                        <p class="border-t border-border px-3 py-2 text-sm text-muted">{{ __('baobab::admin.access.always_granted') }}</p>
                    @else
                        @foreach ($groups as $group)
                            <div class="border-t border-border px-3 py-2">
                                <p class="text-sm font-medium text-foreground">
                                    {{ $group['label'] }}

                                    @unless ($group['active'])
                                        <x-baobab::badge variant="neutral">
                                            {{ __('baobab::admin.access.inactive_module') }}
                                        </x-baobab::badge>
                                    @endunless
                                </p>

                                @foreach ($group['permissions'] as $permission)
                                    <form method="POST" action="{{ route('admin.access.toggle', ['role' => $role, 'permission' => $permission['name']]) }}">
                                        @csrf
                                        <label class="flex min-h-11 items-center gap-3 text-sm text-foreground">
                                            <input
                                                type="checkbox"
                                                class="h-5 w-5 shrink-0"
                                                x-on:change="$el.form.requestSubmit()"
                                                @checked($grants[$role->id][$permission['name']] ?? false)
                                                @disabled(! $group['active'])
                                            >
                                            {{ $permission['label'] }}
                                        </label>
                                    </form>
                                @endforeach
                            </div>
                        @endforeach
                    @endif
                </details>
            @endforeach
        </div>
    </x-baobab::page>
@endsection
