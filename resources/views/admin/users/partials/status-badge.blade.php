@if ($user->isDeactivated())
    <x-baobab::badge variant="danger">{{ __('baobab::admin.users.status.deactivated_badge') }}</x-baobab::badge>
@elseif ($user->hasPendingInvitation())
    <x-baobab::badge variant="warning">{{ __('baobab::admin.users.invite.pending_badge') }}</x-baobab::badge>
@else
    <x-baobab::badge variant="success">{{ __('baobab::admin.users.status.active_badge') }}</x-baobab::badge>
@endif
