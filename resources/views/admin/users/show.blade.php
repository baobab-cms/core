@extends('baobab::layouts.admin')

@section('title', $user->name)

@section('content')
    <x-baobab::page
        :title="$user->name"
        :breadcrumbs="[[__('baobab::admin.users.title'), route('admin.users.index')], [$user->name]]"
    >
        @if ($canImpersonate)
            <x-slot:actions>
                @include('baobab::admin.users.partials.impersonate-button', ['user' => $user])
            </x-slot:actions>
        @endif

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <x-baobab::card :header="__('baobab::admin.users.show.identity_title')">
                <p class="text-sm text-muted">{{ $user->email }}</p>
                <p class="mt-1 text-sm text-muted">
                    {{ __('baobab::admin.users.column_level') }}: {{ $user->level() }}
                </p>
            </x-baobab::card>

            <x-baobab::card :header="__('baobab::admin.users.show.roles_title')">
                @forelse ($user->roles as $role)
                    <p class="border-b border-border py-2 text-sm text-foreground last:border-0">
                        <a href="{{ route('admin.access.roles.show', ['role' => $role]) }}" class="hover:underline">
                            {{ $role->name }}
                        </a>
                    </p>
                @empty
                    <p class="text-sm text-muted">{{ __('baobab::admin.users.show.roles_empty') }}</p>
                @endforelse
            </x-baobab::card>
        </div>

        <x-baobab::card class="mt-6" :header="__('baobab::admin.users.show.direct_permissions_title')">
            <p class="mb-3 text-xs text-muted">{{ __('baobab::admin.users.show.direct_permissions_hint') }}</p>

            @forelse ($directGrants as $grant)
                <div class="flex items-start justify-between gap-4 border-b border-border py-2 last:border-0">
                    <div>
                        <p class="text-sm font-medium text-foreground">{{ $grant->permission->name }}</p>
                        <p class="text-xs text-muted">{{ $grant->justification }}</p>
                        <p class="mt-1 text-xs text-muted">
                            {{ __('baobab::admin.users.show.direct_permission_granted_by', [
                                'name' => $grant->grantedBy->name ?? __('baobab::admin.audit.system_actor'),
                                'date' => $grant->created_at->format('Y-m-d H:i'),
                            ]) }}
                        </p>
                    </div>

                    @if ($canManageAccess)
                        <form method="POST" action="{{ route('admin.users.permissions.destroy', ['user' => $user, 'permission' => $grant->permission->name]) }}">
                            @csrf
                            @method('DELETE')
                            <x-baobab::button type="submit" variant="danger">
                                {{ __('baobab::admin.users.show.direct_permission_revoke_action') }}
                            </x-baobab::button>
                        </form>
                    @endif
                </div>
            @empty
                <p class="text-sm text-muted">{{ __('baobab::admin.users.show.direct_permissions_empty') }}</p>
            @endforelse

            @if ($canManageAccess)
                <x-baobab::form method="POST" action="{{ route('admin.users.permissions.store', ['user' => $user]) }}" class="mt-4 border-t border-border pt-4">
                    <label for="permission" class="mb-1 block text-sm font-medium text-foreground">
                        {{ __('baobab::admin.users.show.direct_permission_select_label') }}
                    </label>
                    <select id="permission" name="permission" class="mb-4 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm">
                        @foreach ($permissionGroups as $group)
                            <optgroup label="{{ $group['label'] }}">
                                @foreach ($group['permissions'] as $permission)
                                    <option value="{{ $permission['name'] }}">{{ $permission['label'] }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>

                    <x-baobab::field.textarea
                        name="justification"
                        label="{{ __('baobab::admin.users.show.direct_permission_justification_label') }}"
                    />

                    <x-baobab::button type="submit" variant="primary">
                        {{ __('baobab::admin.users.show.direct_permission_grant_action') }}
                    </x-baobab::button>
                </x-baobab::form>
            @endif
        </x-baobab::card>

        <x-baobab::card class="mt-6" :header="__('baobab::admin.users.show.sessions_title')">
            <p class="mb-3 text-xs text-muted">{{ __('baobab::admin.users.show.sessions_hint') }}</p>

            @forelse ($activeSessions as $activeSession)
                <div class="flex items-center justify-between gap-4 border-b border-border py-2 last:border-0">
                    <div>
                        <p class="text-sm text-foreground">{{ $activeSession['ip_address'] ?? '—' }}</p>
                        <p class="text-xs text-muted">{{ $activeSession['user_agent'] ?? '—' }}</p>
                        <p class="mt-1 text-xs text-muted">
                            {{ __('baobab::admin.users.show.session_last_activity', ['date' => $activeSession['last_activity_label']]) }}
                        </p>
                    </div>

                    @if ($canImpersonate)
                        <form method="POST" action="{{ route('admin.users.sessions.destroy', ['user' => $user, 'sessionId' => $activeSession['id']]) }}">
                            @csrf
                            @method('DELETE')
                            <x-baobab::button type="submit" variant="danger">
                                {{ __('baobab::admin.users.show.session_revoke_action') }}
                            </x-baobab::button>
                        </form>
                    @endif
                </div>
            @empty
                <p class="text-sm text-muted">{{ __('baobab::admin.users.show.sessions_empty') }}</p>
            @endforelse
        </x-baobab::card>

        <x-baobab::card class="mt-6" :header="__('baobab::admin.users.show.activity_title')">
            <x-baobab::table :columns="$activityColumns" :rows="$activity" />
        </x-baobab::card>
    </x-baobab::page>
@endsection
