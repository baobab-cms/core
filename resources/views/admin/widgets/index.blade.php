@extends('baobab::layouts.admin')

@section('title', __('baobab::admin.widgets.title'))

@section('content')
    <x-baobab::page :title="__('baobab::admin.widgets.title')">
        <div class="grid gap-6 md:grid-cols-2">
            @foreach ($zones as $zone)
                <x-baobab::card :header="$zone->label">
                    <div class="space-y-3">
                        @forelse ($instancesByZone->get($zone->key, []) as $instance)
                            @include('baobab::admin.widgets.partials.instance-row', ['instance' => $instance, 'reorderable' => true])
                        @empty
                            <p class="text-sm text-muted">{{ __('baobab::admin.widgets.zone_empty') }}</p>
                        @endforelse
                    </div>

                    <form method="GET" action="{{ route('admin.widgets.create') }}" class="mt-4 flex items-center gap-2">
                        <input type="hidden" name="zone_key" value="{{ $zone->key }}">
                        <select name="widget_key" class="rounded-md border border-border bg-surface px-3 py-2 text-sm text-foreground">
                            @foreach ($widgetLabels as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <x-baobab::button type="submit" variant="secondary">
                            {{ __('baobab::admin.widgets.add_action') }}
                        </x-baobab::button>
                    </form>
                </x-baobab::card>
            @endforeach

            <x-baobab::card :header="__('baobab::admin.widgets.inactive_zone_label')">
                <div class="space-y-3">
                    @forelse ($inactive as $instance)
                        @include('baobab::admin.widgets.partials.instance-row', ['instance' => $instance, 'reorderable' => false])
                    @empty
                        <p class="text-sm text-muted">{{ __('baobab::admin.widgets.zone_empty') }}</p>
                    @endforelse
                </div>
            </x-baobab::card>
        </div>
    </x-baobab::page>
@endsection
