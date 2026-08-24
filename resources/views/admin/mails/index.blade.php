@extends('baobab::layouts.admin')

@section('title', __('baobab::admin.mails.title'))

@section('content')
    <x-baobab::page :title="__('baobab::admin.mails.title')">
        <x-slot:actions>
            @can('baobab.system.mail.log_view')
                <x-baobab::button :href="route('admin.mails.log')" variant="secondary">
                    {{ __('baobab::admin.mail_log.title') }}
                </x-baobab::button>
            @endcan
        </x-slot:actions>

        <p class="mb-4 text-sm text-muted">{{ __('baobab::admin.mails.intro') }}</p>

        @if ($rows === [])
            <div class="rounded-lg border border-border bg-surface">
                <x-baobab::empty-state :message="__('baobab::admin.mails.empty')" />
            </div>
        @else
            <div class="overflow-hidden rounded-lg border border-border bg-surface">
                <table class="w-full text-sm">
                    <thead class="border-b border-border bg-surface-subtle text-left text-xs uppercase text-muted">
                        <tr>
                            <th class="px-4 py-2 font-medium">{{ __('baobab::admin.mails.column_key') }}</th>
                            <th class="px-4 py-2 font-medium">{{ __('baobab::admin.mails.column_source') }}</th>
                            <th class="px-4 py-2 font-medium">{{ __('baobab::admin.mails.column_description') }}</th>
                            <th class="px-4 py-2 font-medium">{{ __('baobab::admin.mails.column_state') }}</th>
                            <th class="px-4 py-2"><span class="sr-only">{{ __('baobab::admin.mails.edit') }}</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach ($rows as $row)
                            <tr>
                                <td class="px-4 py-2 font-mono font-medium text-foreground">{{ $row['key'] }}</td>
                                <td class="px-4 py-2 text-muted">{{ $row['source'] }}</td>
                                <td class="px-4 py-2 text-foreground">{{ $row['description'] }}</td>
                                <td class="px-4 py-2">
                                    @if ($row['customised'])
                                        <x-baobab::badge variant="info">{{ __('baobab::admin.mails.state_customised') }}</x-baobab::badge>
                                    @else
                                        <span class="text-muted">{{ __('baobab::admin.mails.state_default') }}</span>
                                    @endif

                                    @if ($row['drifted'])
                                        <x-baobab::badge variant="warning" class="ml-2">
                                            {{ __('baobab::admin.mails.drifted') }}
                                        </x-baobab::badge>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-right">
                                    <x-baobab::button :href="route('admin.mails.edit', $row['key'])" variant="ghost">
                                        {{ __('baobab::admin.mails.edit') }}
                                    </x-baobab::button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-baobab::page>
@endsection
