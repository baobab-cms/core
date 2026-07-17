@extends('baobab::layouts.admin')

@section('title', __('baobab::admin.redirects.title'))

@section('content')
    <x-baobab::page :title="__('baobab::admin.redirects.title')">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
            <form method="GET" class="flex items-end gap-2">
                <x-baobab::field.text name="q" label="{{ __('baobab::admin.redirects.search_placeholder') }}" :value="request('q')" />
                <x-baobab::button type="submit" variant="secondary">{{ __('baobab::admin.redirects.search_action') }}</x-baobab::button>
            </form>

            <div class="flex items-center gap-2">
                <x-baobab::button :href="route('admin.redirects.not-found')" variant="secondary">
                    {{ __('baobab::admin.redirects.not_found_action') }}
                </x-baobab::button>
                <x-baobab::button :href="route('admin.redirects.export')" variant="secondary">
                    {{ __('baobab::admin.redirects.export_action') }}
                </x-baobab::button>
                <x-baobab::button :href="route('admin.redirects.create')" variant="primary">
                    {{ __('baobab::admin.redirects.create_title') }}
                </x-baobab::button>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.redirects.import') }}" enctype="multipart/form-data" class="mb-4 flex items-end gap-2">
            @csrf
            <input type="file" name="file" accept=".csv,.txt" required class="text-sm text-foreground">
            <x-baobab::button type="submit" variant="secondary">{{ __('baobab::admin.redirects.import_action') }}</x-baobab::button>
        </form>

        <x-baobab::table :columns="$columns" :rows="$redirects" />
    </x-baobab::page>
@endsection
