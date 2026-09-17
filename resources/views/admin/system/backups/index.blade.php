@extends('baobab::layouts.admin')

@section('title', __('baobab::admin.backups.title'))

@section('content')
    <x-baobab::page :title="__('baobab::admin.backups.title')">
        @can('baobab.system.backups.create')
            <x-baobab::card class="mb-6">
                <h2 class="mb-3 text-sm font-medium text-foreground">{{ __('baobab::admin.backups.settings_title') }}</h2>

                <x-baobab::form method="POST" action="{{ route('admin.system.backups.settings') }}">
                    <x-baobab::field.integer
                        name="retention_daily"
                        :label="__('baobab::admin.backups.retention_daily')"
                        :value="$setting->retention_daily"
                        placeholder="{{ $setting->retentionDaily() }}"
                    />

                    <x-baobab::field.integer
                        name="retention_weekly"
                        :label="__('baobab::admin.backups.retention_weekly')"
                        :value="$setting->retention_weekly"
                        placeholder="{{ $setting->retentionWeekly() }}"
                    />

                    <x-baobab::field.integer
                        name="max_total_size_mb"
                        :label="__('baobab::admin.backups.max_total_size_mb')"
                        :value="$setting->max_total_size_mb"
                        placeholder="{{ $setting->maxTotalSizeMb() ?? __('baobab::admin.backups.max_total_size_unlimited') }}"
                    />

                    <x-baobab::field.checkbox
                        name="scheduled_enabled"
                        :checked="$setting->scheduled_enabled"
                        :label="__('baobab::admin.backups.scheduled_enabled')"
                    />

                    <x-baobab::button type="submit" variant="secondary">
                        {{ __('baobab::admin.backups.save_settings') }}
                    </x-baobab::button>
                </x-baobab::form>
            </x-baobab::card>

            <x-baobab::form method="POST" action="{{ route('admin.system.backups.create') }}" class="mb-6">
                <x-baobab::button type="submit" variant="primary">
                    {{ __('baobab::admin.backups.create_now') }}
                </x-baobab::button>
            </x-baobab::form>
        @endcan

        @if (empty($backups))
            <div class="rounded-lg border border-border bg-surface">
                <x-baobab::empty-state :message="__('baobab::admin.backups.empty')" />
            </div>
        @else
            <x-baobab::table :columns="$columns" :rows="$backups" row-key="token" />
        @endif
    </x-baobab::page>
@endsection
