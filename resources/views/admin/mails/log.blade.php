@extends('baobab::layouts.admin')

@section('title', __('baobab::admin.mail_log.title'))

@section('content')
    <x-baobab::page
        :title="__('baobab::admin.mail_log.title')"
        :breadcrumbs="[[__('baobab::admin.mails.title'), route('admin.mails.index')]]"
    >
        <p class="mb-4 text-sm text-muted">{{ __('baobab::admin.mail_log.intro') }}</p>

        <form method="GET" class="mb-4 flex flex-wrap items-end gap-2">
            <x-baobab::field.select
                name="template_key"
                :label="__('baobab::admin.mail_log.filter_template')"
                :options="$templateOptions"
                :value="request('template_key')"
            />
            <x-baobab::field.select
                name="status"
                :label="__('baobab::admin.mail_log.filter_status')"
                :options="$statusOptions"
                :value="request('status')"
            />
            <x-baobab::field.text
                name="recipient"
                :label="__('baobab::admin.mail_log.filter_recipient')"
                :value="request('recipient')"
            />
            <x-baobab::field.date name="from" :label="__('baobab::admin.mail_log.filter_from')" :value="request('from')" />
            <x-baobab::field.date name="to" :label="__('baobab::admin.mail_log.filter_to')" :value="request('to')" />

            <div class="mb-4 flex gap-2">
                <x-baobab::button type="submit" variant="secondary">
                    {{ __('baobab::admin.mail_log.filter_submit') }}
                </x-baobab::button>

                @if (request()->hasAny(['template_key', 'status', 'recipient', 'from', 'to']))
                    <x-baobab::button :href="route('admin.mails.log')" variant="ghost">
                        {{ __('baobab::admin.mail_log.filter_reset') }}
                    </x-baobab::button>
                @endif
            </div>
        </form>

        @if ($entries->isEmpty())
            <div class="rounded-lg border border-border bg-surface">
                <x-baobab::empty-state :message="__('baobab::admin.mail_log.empty')" />
            </div>
        @else
            <x-baobab::table :columns="$columns" :rows="$entries" />
        @endif
    </x-baobab::page>
@endsection
