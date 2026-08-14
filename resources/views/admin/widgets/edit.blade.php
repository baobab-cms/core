@extends('baobab::layouts.admin')

@section('title', $widgetLabel)

@section('content')
    <x-baobab::page :title="$widgetLabel">
        <x-baobab::card>
            <x-baobab::form method="POST" action="{{ route('admin.widgets.update', ['instance' => $instance->id]) }}">
                <x-baobab::field.select
                    name="zone_key"
                    :label="__('baobab::admin.widgets.zone_label')"
                    :options="$zones->pluck('label', 'key')"
                    :value="$instance->zone_key"
                />

                <x-baobab::field.select
                    name="visibility"
                    :label="__('baobab::admin.widgets.visibility_label')"
                    :options="[
                        'everyone' => __('baobab::admin.widgets.visibility_everyone'),
                        'guests' => __('baobab::admin.widgets.visibility_guests'),
                        'authenticated' => __('baobab::admin.widgets.visibility_authenticated'),
                    ]"
                    :value="$instance->visibility"
                />

                <x-baobab::field.checkbox
                    name="is_active"
                    :label="__('baobab::admin.widgets.is_active_label')"
                    :checked="$instance->is_active"
                />

                @include('baobab::admin.widgets.partials.settings-fields', ['fields' => $fields])

                <x-baobab::button type="submit" variant="primary">
                    {{ __('baobab::admin.widgets.update_action') }}
                </x-baobab::button>
            </x-baobab::form>
        </x-baobab::card>
    </x-baobab::page>
@endsection
