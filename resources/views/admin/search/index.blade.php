@extends('baobab::layouts.admin')

@section('title', __('baobab::admin.search.title'))

@section('content')
    <x-baobab::page :title="__('baobab::admin.search.title')">
        <x-baobab::card class="mb-6">
            <x-slot:header>
                <span class="font-medium text-foreground">{{ __('baobab::admin.search.driver_title') }}</span>
            </x-slot:header>

            <p class="text-sm text-foreground">{{ $driver }}</p>

            @if ($driver === 'database')
                <p class="mt-2 text-xs text-muted">{{ __('baobab::admin.search.database_driver_note') }}</p>
            @endif
        </x-baobab::card>

        <x-baobab::card class="mb-6">
            <x-slot:header>
                <span class="font-medium text-foreground">{{ __('baobab::admin.search.sources_title') }}</span>
            </x-slot:header>

            <table class="w-full text-left text-sm">
                <thead class="text-xs uppercase text-muted">
                    <tr>
                        <th class="py-2 font-medium">{{ __('baobab::admin.search.column_key') }}</th>
                        <th class="py-2 font-medium">{{ __('baobab::admin.search.column_label') }}</th>
                        <th class="py-2 font-medium">{{ __('baobab::admin.search.column_contexts') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @foreach ($sources as $source)
                        <tr>
                            <td class="py-2 font-mono text-xs">{{ $source['key'] }}</td>
                            <td class="py-2">{{ $source['label'] }}</td>
                            <td class="py-2 text-muted">{{ $source['contexts'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-baobab::card>

        <x-baobab::card>
            <x-slot:header>
                <span class="font-medium text-foreground">{{ __('baobab::admin.search.index_title') }}</span>
            </x-slot:header>

            @if ($contentTypes === [])
                <p class="text-sm text-muted">{{ __('baobab::admin.search.no_searchable_types') }}</p>
            @else
                <table class="mb-4 w-full text-left text-sm">
                    <thead class="text-xs uppercase text-muted">
                        <tr>
                            <th class="py-2 font-medium">{{ __('baobab::admin.search.column_type') }}</th>
                            <th class="py-2 font-medium">{{ __('baobab::admin.search.column_fields') }}</th>
                            <th class="py-2 font-medium">{{ __('baobab::admin.search.column_volume') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach ($contentTypes as $type)
                            <tr>
                                <td class="py-2">{{ $type['key'] }}</td>
                                <td class="py-2 font-mono text-xs">{{ $type['searchable_fields'] }}</td>
                                <td class="py-2">{{ $type['total'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <x-baobab::form method="POST" action="{{ route('admin.search.reindex') }}">
                    <x-baobab::button type="submit" variant="secondary">{{ __('baobab::admin.search.reindex_action') }}</x-baobab::button>
                </x-baobab::form>
            @endif
        </x-baobab::card>
    </x-baobab::page>
@endsection
