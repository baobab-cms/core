@extends('baobab::layouts.admin')

@section('title', __('baobab::admin.access.direct_permissions.title'))

@section('content')
    <x-baobab::page
        :title="__('baobab::admin.access.direct_permissions.title')"
        :breadcrumbs="[[__('baobab::admin.access.title'), route('admin.access.index')], [__('baobab::admin.access.direct_permissions.title')]]"
    >
        <p class="mb-4 text-sm text-muted">{{ __('baobab::admin.access.direct_permissions.hint') }}</p>

        <div class="overflow-x-auto rounded-lg border border-border">
            <table class="w-full text-left text-sm">
                <thead class="bg-surface-subtle text-xs uppercase text-muted">
                    <tr>
                        <th class="px-3 py-2 font-medium">{{ __('baobab::admin.access.direct_permissions.column_user') }}</th>
                        <th class="px-3 py-2 font-medium">{{ __('baobab::admin.access.direct_permissions.column_permission') }}</th>
                        <th class="px-3 py-2 font-medium">{{ __('baobab::admin.access.direct_permissions.column_justification') }}</th>
                        <th class="px-3 py-2 font-medium">{{ __('baobab::admin.access.direct_permissions.column_granted_by') }}</th>
                        <th class="px-3 py-2 font-medium">{{ __('baobab::admin.access.direct_permissions.column_granted_at') }}</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-border">
                    @forelse ($grants as $grant)
                        <tr>
                            <td class="px-3 py-2 text-foreground">
                                <a href="{{ route('admin.users.show', ['user' => $grant->user]) }}" class="hover:underline">
                                    {{ $grant->user->name }}
                                </a>
                            </td>
                            <td class="px-3 py-2 text-foreground">{{ $grant->permission->name }}</td>
                            <td class="px-3 py-2 text-foreground">{{ $grant->justification }}</td>
                            <td class="px-3 py-2 text-foreground">{{ $grant->grantedBy->name ?? __('baobab::admin.audit.system_actor') }}</td>
                            <td class="px-3 py-2 text-foreground">{{ $grant->created_at->format('Y-m-d H:i') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-3 py-2 text-muted">{{ __('baobab::admin.access.direct_permissions.empty') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $grants->links() }}
        </div>
    </x-baobab::page>
@endsection
