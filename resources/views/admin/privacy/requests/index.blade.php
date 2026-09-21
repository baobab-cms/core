@extends('baobab::layouts.admin')

@section('title', __('baobab::admin.privacy_requests.title'))

@section('content')
    <x-baobab::page :title="__('baobab::admin.privacy_requests.title')">
        <p class="mb-4 text-sm text-muted">{{ __('baobab::admin.privacy_requests.intro') }}</p>

        <x-baobab::card class="mb-6">
            <h2 class="mb-3 text-sm font-medium text-foreground">{{ __('baobab::admin.privacy_requests.new_title') }}</h2>

            <x-baobab::form method="POST" action="{{ route('admin.privacy.requests.store') }}">
                <x-baobab::field.text
                    name="subject"
                    :label="__('baobab::admin.privacy_requests.subject_label')"
                    :placeholder="__('baobab::admin.privacy_requests.subject_placeholder')"
                />

                <div class="flex flex-wrap gap-2">
                    <x-baobab::button type="submit" name="type" value="export" variant="primary">
                        {{ __('baobab::admin.privacy_requests.submit_export') }}
                    </x-baobab::button>
                    <x-baobab::button type="submit" name="type" value="erasure" variant="danger">
                        {{ __('baobab::admin.privacy_requests.submit_erasure', ['days' => $graceDays]) }}
                    </x-baobab::button>
                </div>
            </x-baobab::form>
        </x-baobab::card>

        <h2 class="mb-3 text-sm font-medium text-foreground">{{ __('baobab::admin.privacy_requests.history_title') }}</h2>

        @if ($requests->isEmpty())
            <div class="rounded-lg border border-border bg-surface">
                <x-baobab::empty-state :message="__('baobab::admin.privacy_requests.empty')" />
            </div>
        @else
            <x-baobab::table :columns="$columns" :rows="$requests" row-key="uuid" />
        @endif
    </x-baobab::page>
@endsection
