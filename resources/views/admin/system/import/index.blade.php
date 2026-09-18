@extends('baobab::layouts.admin')

@section('title', __('baobab::admin.import.title'))

@section('content')
    <x-baobab::page :title="__('baobab::admin.import.title')">
        @can('baobab.system.import.run')
            <x-baobab::card class="mb-6">
                <x-baobab::form
                    method="POST"
                    action="{{ route('admin.system.import.preview') }}"
                    enctype="multipart/form-data"
                    class="space-y-3"
                >
                    <div class="space-y-1">
                        <label for="import-archive" class="block text-sm font-medium text-foreground">
                            {{ __('baobab::admin.import.archive_field') }}
                        </label>

                        <input
                            id="import-archive"
                            type="file"
                            name="archive"
                            accept=".zip,application/zip"
                            required
                            class="block w-full text-sm text-foreground file:mr-3 file:rounded-md file:border file:border-border file:bg-surface file:px-3 file:py-1.5 file:text-sm file:font-medium"
                        >

                        <x-baobab::field.error name="archive" />
                    </div>

                    <x-baobab::field.select
                        name="strategy"
                        :label="__('baobab::admin.import.strategy_label')"
                        :options="[
                            'ignore' => __('baobab::admin.import.strategy_ignore'),
                            'replace' => __('baobab::admin.import.strategy_replace'),
                            'duplicate' => __('baobab::admin.import.strategy_duplicate'),
                        ]"
                    />

                    <x-baobab::button type="submit" variant="secondary">
                        {{ __('baobab::admin.import.preview_action') }}
                    </x-baobab::button>
                </x-baobab::form>
            </x-baobab::card>

            @if ($report)
                <x-baobab::card class="mb-6">
                    <h2 class="mb-3 text-sm font-medium text-foreground">{{ __('baobab::admin.import.report_title') }}</h2>

                    @if ($report['errors'] !== [])
                        <ul class="mb-3 list-inside list-disc text-sm text-danger">
                            @foreach ($report['errors'] as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    @else
                        <ul class="mb-3 divide-y divide-border text-sm">
                            @foreach ($report['content_types'] as $summary)
                                <li class="flex items-center justify-between py-2">
                                    <span class="font-medium text-foreground">{{ $summary['key'] }}</span>

                                    @if ($summary['will_create'])
                                        <x-baobab::badge variant="info">{{ __('baobab::admin.import.will_create') }}</x-baobab::badge>
                                    @else
                                        <span class="text-muted">
                                            {{ __('baobab::admin.import.row_summary', [
                                                'created' => $summary['created'],
                                                'updated' => $summary['updated'],
                                                'skipped' => $summary['skipped'],
                                                'duplicated' => $summary['duplicated'],
                                            ]) }}
                                        </span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>

                        <p class="mb-4 text-sm text-muted">
                            {{ __('baobab::admin.import.media_summary', ['matched' => $report['media_matched'], 'toImport' => $report['media_to_import']]) }}
                        </p>

                        @if ($pending)
                            <x-baobab::form method="POST" action="{{ route('admin.system.import.create') }}">
                                <x-baobab::button type="submit" variant="primary">
                                    {{ __('baobab::admin.import.confirm_action') }}
                                </x-baobab::button>
                            </x-baobab::form>
                        @endif
                    @endif
                </x-baobab::card>
            @endif
        @endcan

        <h2 class="mb-3 text-sm font-medium text-foreground">{{ __('baobab::admin.import.history_title') }}</h2>

        @if ($jobs->isEmpty())
            <div class="rounded-lg border border-border bg-surface">
                <x-baobab::empty-state :message="__('baobab::admin.import.empty')" />
            </div>
        @else
            <x-baobab::table :columns="$columns" :rows="$jobs" row-key="uuid" />
        @endif
    </x-baobab::page>
@endsection
