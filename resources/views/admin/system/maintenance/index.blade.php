@extends('baobab::layouts.admin')

@section('title', __('baobab::admin.maintenance.title'))

@section('content')
    <x-baobab::page :title="__('baobab::admin.maintenance.title')">
        @if ($secret)
            <div class="mb-4 rounded-md border border-warning-200 bg-warning-50 px-4 py-3 text-sm text-warning-700">
                <p class="font-medium">{{ __('baobab::admin.maintenance.secret_shown_once') }}</p>
                <p class="mt-1 font-mono">{{ url('/'.$secret) }}</p>
            </div>
        @endif

        <x-baobab::card>
            <p class="mb-4 text-sm text-foreground">
                {{ $active ? __('baobab::admin.maintenance.status_active') : __('baobab::admin.maintenance.status_inactive') }}
            </p>

            @if ($active)
                @if (($data['retry'] ?? null) !== null)
                    <p class="mb-4 text-sm text-muted">{{ __('baobab::admin.maintenance.retry_configured', ['seconds' => $data['retry']]) }}</p>
                @endif

                <x-baobab::form method="POST" action="{{ route('admin.system.maintenance.deactivate') }}">
                    <x-baobab::button type="submit" variant="primary">{{ __('baobab::admin.maintenance.deactivate_action') }}</x-baobab::button>
                </x-baobab::form>
            @else
                <x-baobab::form method="POST" action="{{ route('admin.system.maintenance.activate') }}">
                    <x-baobab::field.integer
                        name="retry"
                        :label="__('baobab::admin.maintenance.retry_label')"
                    />

                    <x-baobab::field.text
                        name="redirect"
                        :label="__('baobab::admin.maintenance.redirect_label')"
                    />

                    <x-baobab::field.checkbox
                        name="generate_secret"
                        :checked="true"
                        :label="__('baobab::admin.maintenance.generate_secret_label')"
                    />

                    <x-baobab::button type="submit" variant="primary">{{ __('baobab::admin.maintenance.activate_action') }}</x-baobab::button>
                </x-baobab::form>
            @endif
        </x-baobab::card>
    </x-baobab::page>
@endsection
