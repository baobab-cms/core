@extends('baobab::layouts.admin')

@section('title', __('baobab::admin.content_types.title'))

@section('content')
    <x-baobab::page :title="__('baobab::admin.content_types.title')">
        <x-slot:actions>
            <x-baobab::button :href="route('admin.content-types.create')" variant="primary">
                {{ __('baobab::admin.content_types.new') }}
            </x-baobab::button>
        </x-slot:actions>

        <p class="mb-4 text-sm text-muted">{{ __('baobab::admin.content_types.intro') }}</p>

        @if ($rows->isEmpty())
            <div class="rounded-lg border border-border bg-surface">
                <x-baobab::empty-state :message="__('baobab::admin.content_types.empty')">
                    <x-slot:action>
                        <x-baobab::button :href="route('admin.content-types.create')" variant="primary">
                            {{ __('baobab::admin.content_types.new') }}
                        </x-baobab::button>
                    </x-slot:action>
                </x-baobab::empty-state>
            </div>
        @else
            <div class="overflow-hidden rounded-lg border border-border bg-surface">
                <table class="w-full text-sm">
                    <thead class="border-b border-border bg-surface-subtle text-left text-xs uppercase text-muted">
                        <tr>
                            <th class="px-4 py-2 font-medium">{{ __('baobab::admin.content_types.column_key') }}</th>
                            <th class="px-4 py-2 font-medium">{{ __('baobab::admin.content_types.column_label') }}</th>
                            <th class="px-4 py-2 font-medium">{{ __('baobab::admin.content_types.column_table') }}</th>
                            <th class="px-4 py-2 font-medium">{{ __('baobab::admin.content_types.column_addressable') }}</th>
                            <th class="px-4 py-2 font-medium">{{ __('baobab::admin.content_types.column_version') }}</th>
                            <th class="px-4 py-2"><span class="sr-only">{{ __('baobab::admin.content_types.edit') }}</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach ($rows as $row)
                            <tr>
                                <td class="px-4 py-2 font-mono font-medium text-foreground">
                                    {{ $row['key'] }}
                                    @unless ($row['built'])
                                        <x-baobab::badge variant="warning" class="ml-2">
                                            {{ __('baobab::admin.content_types.not_built') }}
                                        </x-baobab::badge>
                                    @endunless
                                </td>
                                <td class="px-4 py-2 text-foreground">{{ $row['label'] }}</td>
                                <td class="px-4 py-2 font-mono text-muted">{{ $row['table'] }}</td>
                                <td class="px-4 py-2">
                                    @if ($row['addressable'])
                                        <x-baobab::badge variant="success">{{ __('baobab::admin.content_types.addressable_yes') }}</x-baobab::badge>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-muted">v{{ $row['version'] }}</td>
                                <td class="px-4 py-2 text-right">
                                    <x-baobab::button :href="route('admin.content-types.edit', $row['model'])" variant="ghost">
                                        {{ __('baobab::admin.content_types.edit') }}
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
