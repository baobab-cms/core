@extends('baobab::layouts.admin')

@section('title', __('baobab::admin.redirects.edit_title'))

@section('content')
    <x-baobab::page :title="__('baobab::admin.redirects.edit_title')">
        <x-baobab::card>
            <x-baobab::form method="PUT" action="{{ route('admin.redirects.update', ['redirect' => $redirect->id]) }}">
                <x-baobab::field.text name="source" :label="__('baobab::admin.redirects.source_label')" :value="$redirect->source" />
                <x-baobab::field.text name="target" :label="__('baobab::admin.redirects.target_label')" :value="$redirect->target" />

                <x-baobab::field.select
                    name="status_code"
                    :label="__('baobab::admin.redirects.status_code_label')"
                    :options="[301 => '301', 302 => '302', 410 => '410 (gone)']"
                    :value="$redirect->status_code"
                />

                <x-baobab::field.checkbox name="is_active" :label="__('baobab::admin.redirects.active_label')" :checked="$redirect->is_active" />

                <x-baobab::button type="submit" variant="primary">{{ __('baobab::admin.redirects.save_action') }}</x-baobab::button>
            </x-baobab::form>
        </x-baobab::card>
    </x-baobab::page>
@endsection
