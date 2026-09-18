@extends('baobab::layouts.admin')

@section('title', __('baobab::admin.export.title'))

@section('content')
    <x-baobab::page :title="__('baobab::admin.export.title')">
        @can('baobab.system.export.create')
            <x-baobab::card class="mb-6">
                <x-baobab::form method="POST" action="{{ route('admin.system.export.create') }}">
                    <fieldset class="mb-4">
                        <legend class="mb-2 text-sm font-medium text-foreground">
                            {{ __('baobab::admin.export.content_types_label') }}
                        </legend>

                        @if ($contentTypes->isEmpty())
                            <p class="text-sm text-muted">{{ __('baobab::admin.export.empty_selection') }}</p>
                        @else
                            <div class="space-y-2">
                                @foreach ($contentTypes as $contentType)
                                    <label class="flex items-center gap-2 text-sm text-foreground">
                                        <input
                                            type="checkbox"
                                            name="content_type_keys[]"
                                            value="{{ $contentType->key }}"
                                            @checked(in_array($contentType->key, old('content_type_keys', []), true))
                                            class="rounded border-border"
                                        >
                                        {{ $contentType->key }}
                                    </label>
                                @endforeach
                            </div>
                        @endif

                        <x-baobab::field.error name="content_type_keys" />
                    </fieldset>

                    <x-baobab::button type="submit" variant="primary">
                        {{ __('baobab::admin.export.submit') }}
                    </x-baobab::button>
                </x-baobab::form>
            </x-baobab::card>
        @endcan

        <h2 class="mb-3 text-sm font-medium text-foreground">{{ __('baobab::admin.export.history_title') }}</h2>

        @if ($jobs->isEmpty())
            <div class="rounded-lg border border-border bg-surface">
                <x-baobab::empty-state :message="__('baobab::admin.export.empty')" />
            </div>
        @else
            <x-baobab::table :columns="$columns" :rows="$jobs" row-key="uuid" />
        @endif
    </x-baobab::page>
@endsection
