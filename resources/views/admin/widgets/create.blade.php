@extends('baobab::layouts.admin')

@section('title', __('baobab::admin.widgets.create_title'))

@section('content')
    <x-baobab::page :title="__('baobab::admin.widgets.create_title')">
        <x-baobab::card>
            @if ($widgetKey === null)
                <p class="text-sm text-muted">{{ __('baobab::admin.widgets.pick_widget_hint') }}</p>
                <x-baobab::button href="{{ route('admin.widgets.index') }}" variant="secondary" class="mt-3">
                    {{ __('baobab::admin.widgets.back_action') }}
                </x-baobab::button>
            @else
                <x-baobab::form method="POST" action="{{ route('admin.widgets.store') }}">
                    <input type="hidden" name="widget_key" value="{{ $widgetKey }}">

                    <x-baobab::field.select
                        name="zone_key"
                        :label="__('baobab::admin.widgets.zone_label')"
                        :options="$zones->pluck('label', 'key')"
                        :value="$zoneKey"
                    />

                    <x-baobab::field.select
                        name="visibility"
                        :label="__('baobab::admin.widgets.visibility_label')"
                        :options="[
                            'everyone' => __('baobab::admin.widgets.visibility_everyone'),
                            'guests' => __('baobab::admin.widgets.visibility_guests'),
                            'authenticated' => __('baobab::admin.widgets.visibility_authenticated'),
                        ]"
                    />

                    @include('baobab::admin.widgets.partials.settings-fields', ['fields' => $fields])

                    <x-baobab::button type="submit" variant="primary">
                        {{ __('baobab::admin.widgets.create_action') }}
                    </x-baobab::button>
                </x-baobab::form>
            @endif
        </x-baobab::card>
    </x-baobab::page>
@endsection
