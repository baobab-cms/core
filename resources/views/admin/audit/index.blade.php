@extends('baobab::layouts.admin')

@section('title', __('baobab::admin.audit.title'))

@section('content')
    <x-baobab::page :title="__('baobab::admin.audit.title')">
        <form method="GET" class="mb-4 flex flex-wrap items-end gap-2">
            <x-baobab::field.text name="action" label="{{ __('baobab::admin.audit.filter_action') }}" :value="request('action')" />
            <x-baobab::field.text name="actor" label="{{ __('baobab::admin.audit.filter_actor') }}" :value="request('actor')" />
            <x-baobab::field.select
                name="auditable_type"
                :label="__('baobab::admin.audit.filter_object_type')"
                :options="$objectTypeOptions"
                :value="request('auditable_type')"
            />
            <x-baobab::field.date name="from" :label="__('baobab::admin.audit.filter_from')" :value="request('from')" />
            <x-baobab::field.date name="to" :label="__('baobab::admin.audit.filter_to')" :value="request('to')" />
            <x-baobab::field.checkbox
                name="impersonations_only"
                :checked="request()->boolean('impersonations_only')"
                :label="__('baobab::admin.audit.filter_impersonations_only')"
            />

            <div class="mb-4 flex gap-2">
                <x-baobab::button type="submit" variant="secondary">
                    {{ __('baobab::admin.audit.filter_submit') }}
                </x-baobab::button>

                @if (request()->hasAny(['action', 'actor', 'auditable_type', 'from', 'to', 'impersonations_only']))
                    <x-baobab::button :href="route('admin.audit.index')" variant="ghost">
                        {{ __('baobab::admin.audit.filter_reset') }}
                    </x-baobab::button>
                @endif

                @can('baobab.audit.export')
                    <x-baobab::button :href="route('admin.audit.export', request()->query())" variant="secondary">
                        {{ __('baobab::admin.audit.export_action') }}
                    </x-baobab::button>
                @endcan
            </div>
        </form>

        @if ($entries->isEmpty())
            <div class="rounded-lg border border-border bg-surface">
                <x-baobab::empty-state :message="__('baobab::admin.audit.empty')" />
            </div>
        @else
            <x-baobab::table :columns="$columns" :rows="$entries" />
        @endif
    </x-baobab::page>
@endsection
