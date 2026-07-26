@extends('baobab::layouts.admin')

@section('title', __('baobab::admin.themes.studio.edit_title', ['slug' => $slug]))

@section('content')
    <x-baobab::page :title="__('baobab::admin.themes.studio.edit_title', ['slug' => $slug])">
        <x-baobab::card>
            <x-baobab::form method="POST" action="{{ route('admin.themes.studio.update', ['slug' => $slug]) }}">
                <x-baobab::field.text name="name" :label="__('baobab::admin.themes.studio.name_label')" :value="$blueprint['name'] ?? ''" />

                <div class="mb-4">
                    <span class="mb-1 block text-sm font-medium text-foreground">{{ __('baobab::admin.themes.studio.slug_label') }}</span>
                    <p class="text-sm text-muted">{{ $slug }}</p>
                </div>

                <x-baobab::field.textarea
                    name="menus"
                    :label="__('baobab::admin.themes.studio.menus_label')"
                    :value="$menusText"
                    rows="4"
                    placeholder="primary: Navigation principale"
                />

                <x-baobab::field.textarea
                    name="widget_zones"
                    :label="__('baobab::admin.themes.studio.widget_zones_label')"
                    :value="$widgetZonesText"
                    rows="4"
                    placeholder="sidebar: Barre latérale"
                />

                <div class="mb-4">
                    <span class="mb-1 block text-sm font-medium text-foreground">{{ __('baobab::admin.themes.studio.supports_label') }}</span>

                    <label class="flex items-center gap-2 text-sm text-foreground">
                        <input type="checkbox" name="supports[]" value="search" @checked(in_array('search', old('supports', $blueprint['supports'] ?? []), true)) class="rounded border-border">
                        {{ __('baobab::admin.themes.studio.supports_search') }}
                    </label>

                    <label class="mt-1 flex items-center gap-2 text-sm text-muted">
                        <input type="checkbox" disabled class="rounded border-border">
                        {{ __('baobab::admin.themes.studio.supports_forms') }}
                    </label>

                    <label class="mt-1 flex items-center gap-2 text-sm text-muted">
                        <input type="checkbox" disabled class="rounded border-border">
                        {{ __('baobab::admin.themes.studio.supports_cookie_banner') }}
                    </label>

                    <label class="mt-1 flex items-center gap-2 text-sm text-muted">
                        <input type="checkbox" disabled class="rounded border-border">
                        {{ __('baobab::admin.themes.studio.supports_maintenance') }}
                    </label>
                </div>

                <x-baobab::button type="submit" variant="primary">
                    {{ __('baobab::admin.themes.studio.update_action') }}
                </x-baobab::button>
            </x-baobab::form>
        </x-baobab::card>
    </x-baobab::page>
@endsection
