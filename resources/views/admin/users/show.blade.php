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

                @if ($user->hasPendingInvitation())
                    <p class="mt-3 text-sm font-medium text-warning">
                        {{ __('baobab::admin.users.invite.pending_since', ['date' => $user->invited_at?->format('Y-m-d H:i')]) }}
                    </p>

                    @if ($canResendInvitation)
                        <div class="mt-2 flex flex-wrap gap-2">
                            <x-baobab::form method="POST" action="{{ route('admin.users.invitation.resend', ['user' => $user]) }}">
                                <x-baobab::button type="submit" variant="secondary">
                                    {{ __('baobab::admin.users.invite.resend_action') }}
                                </x-baobab::button>
                            </x-baobab::form>

                            <x-baobab::button type="button" variant="danger" x-on:click="$dispatch('open-modal', 'cancel-invitation')">
                                {{ __('baobab::admin.users.invite.cancel_action') }}
                            </x-baobab::button>
                        </div>

                        <x-baobab::confirm name="cancel-invitation" :title="__('baobab::admin.users.invite.cancel_confirm_title')">
                            <x-slot:description>
                                {{ __('baobab::admin.users.invite.cancel_confirm_description', ['email' => $user->email]) }}
                            </x-slot:description>

                            <x-baobab::form method="DELETE" action="{{ route('admin.users.invitation.cancel', ['user' => $user]) }}">
                                <x-baobab::button type="submit" variant="danger">
                                    {{ __('baobab::admin.users.invite.cancel_action') }}
                                </x-baobab::button>
                            </x-baobab::form>
                        </x-baobab::confirm>
                    @endif
                @endif
            </x-baobab::card>

            <x-baobab::card :header="__('baobab::admin.users.show.roles_title')">
                @forelse ($user->roles as $role)
                    <div class="flex items-center justify-between gap-4 border-b border-border py-2 last:border-0">
                        <a href="{{ route('admin.access.roles.show', ['role' => $role]) }}" class="text-sm text-foreground hover:underline">
                            {{ $role->name }}
                        </a>

                        @if (in_array($role->id, $revocableRoleIds, true))
                            <form method="POST" action="{{ route('admin.users.roles.destroy', ['user' => $user, 'role' => $role]) }}">
                                @csrf
                                @method('DELETE')
                                <x-baobab::button type="submit" variant="danger">
                                    {{ __('baobab::admin.users.roles.revoke_action') }}
                                </x-baobab::button>
                            </form>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-muted">{{ __('baobab::admin.users.show.roles_empty') }}</p>
                @endforelse

                @if ($canManageRoles && $grantableRoles->isNotEmpty())
                    <x-baobab::form method="POST" action="{{ route('admin.users.roles.store', ['user' => $user]) }}" class="mt-4 flex items-end gap-2 border-t border-border pt-4">
                        <div class="flex-1">
                            <label for="grant-role" class="mb-1 block text-sm font-medium text-foreground">
                                {{ __('baobab::admin.users.roles.grant_label') }}
                            </label>
                            <select id="grant-role" name="role" class="w-full rounded-md border border-border bg-surface px-3 py-2 text-sm">
                                @foreach ($grantableRoles as $grantable)
                                    <option value="{{ $grantable->id }}">{{ $grantable->name }} ({{ $grantable->level }})</option>
                                @endforeach
                            </select>
                        </div>

                        <x-baobab::button type="submit" variant="primary">
                            {{ __('baobab::admin.users.roles.grant_action') }}
                        </x-baobab::button>
                    </x-baobab::form>
                @endif
            </x-baobab::card>
        </div>

        @if ($canEditProfile)
            <x-baobab::card class="mt-6" :header="__('baobab::admin.users.profile.title')">
                <p class="mb-4 text-sm text-muted">{{ __('baobab::admin.users.profile.intro') }}</p>

                <x-baobab::form
                    method="PUT"
                    action="{{ route('admin.users.profile.update', ['user' => $user]) }}"
                    class="max-w-sm"
                    x-data="{ lostMailbox: {{ old('lost_mailbox') ? 'true' : 'false' }} }"
                >
                    <x-baobab::field.text
                        name="name"
                        id="profile_name"
                        bag="updateProfile"
                        :value="$user->name"
                        label="{{ __('baobab::admin.users.profile.name_label') }}"
                    />
                    <x-baobab::field.text
                        type="email"
                        name="email"
                        id="profile_email"
                        bag="updateProfile"
                        :value="$user->email"
                        :readonly="$user->hasPendingInvitation()"
                        label="{{ __('baobab::admin.users.profile.email_label') }}"
                    />

                    @if ($user->hasPendingInvitation())
                        <p class="mb-4 text-xs text-muted">{{ __('baobab::admin.account.profile.invitation_pending') }}</p>
                    @else
                        <label class="mb-2 flex items-start gap-2 text-sm text-foreground">
                            <input type="checkbox" name="lost_mailbox" value="1" x-model="lostMailbox" class="mt-1 rounded border-border">
                            <span>{{ __('baobab::admin.users.profile.lost_mailbox_toggle') }}</span>
                        </label>

                        <div x-show="lostMailbox" x-cloak class="mb-2">
                            <p class="mb-3 text-xs text-muted">{{ __('baobab::admin.users.profile.lost_mailbox_hint') }}</p>
                            <x-baobab::field.text
                                type="password"
                                name="admin_password"
                                id="profile_admin_password"
                                bag="updateProfile"
                                autocomplete="current-password"
                                label="{{ __('baobab::admin.users.profile.admin_password_label') }}"
                            />
                            <x-baobab::field.text
                                name="justification"
                                id="profile_justification"
                                bag="updateProfile"
                                label="{{ __('baobab::admin.users.profile.justification_label') }}"
                            />
                        </div>
                    @endif

                    <x-baobab::button type="submit" variant="primary">
                        {{ __('baobab::admin.users.profile.submit') }}
                    </x-baobab::button>
                </x-baobab::form>
            </x-baobab::card>
        @endif

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
