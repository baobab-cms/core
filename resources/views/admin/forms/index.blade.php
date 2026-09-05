@extends('baobab::layouts.admin')

@section('title', __('baobab::admin.forms.title'))

@section('content')
    <x-baobab::page :title="__('baobab::admin.forms.title')">
        <div class="mb-4 flex justify-end">
            <x-baobab::button :href="route('admin.forms.create')" variant="primary">
                {{ __('baobab::admin.forms.create_title') }}
            </x-baobab::button>
        </div>

        <x-baobab::table :columns="$columns" :rows="$forms" />

        <x-baobab::card class="mt-6" :header="__('baobab::admin.forms.import_title')">
            <x-baobab::form method="POST" action="{{ route('admin.forms.import') }}" enctype="multipart/form-data" class="space-y-3">
                <div class="space-y-1">
                    <label for="form-import-file" class="block text-sm font-medium text-foreground">
                        {{ __('baobab::admin.forms.import_label') }}
                    </label>

                    <input
                        id="form-import-file"
                        type="file"
                        name="file"
                        accept=".json,application/json"
                        required
                        class="block w-full text-sm text-foreground file:mr-3 file:rounded-md file:border file:border-border file:bg-surface file:px-3 file:py-1.5 file:text-sm file:font-medium"
                    >

                    <p class="text-xs text-muted">{{ __('baobab::admin.forms.import_hint') }}</p>
                </div>

                <x-baobab::button type="submit" variant="secondary">
                    {{ __('baobab::admin.forms.import_action') }}
                </x-baobab::button>
            </x-baobab::form>
        </x-baobab::card>
    </x-baobab::page>
@endsection
