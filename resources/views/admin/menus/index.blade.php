@extends('baobab::layouts.admin')

@section('title', __('baobab::admin.menus.title'))

@section('content')
    <x-baobab::page :title="__('baobab::admin.menus.title')">
        <x-slot:actions>
            <x-baobab::button href="{{ route('admin.menus.create') }}" variant="primary">
                {{ __('baobab::admin.menus.create_action') }}
            </x-baobab::button>
        </x-slot:actions>

        @if ($menus->isEmpty())
            <x-baobab::empty-state :message="__('baobab::admin.menus.empty')" />
        @else
            <div class="space-y-4">
                @foreach ($menus as $menu)
                    <x-baobab::card :header="$menu->name">
                        <div class="flex items-center justify-between gap-4">
                            <div class="space-y-1 text-sm text-muted">
                                <p>{{ __('baobab::admin.menus.items_count', ['count' => $menu->items_count]) }}</p>
                                @if ($menu->assignments->isNotEmpty())
                                    <p>{{ __('baobab::admin.menus.assigned_to') }} {{ $menu->assignments->pluck('location_key')->implode(', ') }}</p>
                                @endif
                            </div>

                            <div class="flex items-center gap-3">
                                <a href="{{ route('admin.menus.edit', ['menu' => $menu->id]) }}" class="text-primary hover:underline">
                                    {{ __('baobab::admin.menus.edit_action') }}
                                </a>

                                <x-baobab::delete-action
                                    name="delete-menu-{{ $menu->id }}"
                                    :action="route('admin.menus.destroy', ['menu' => $menu->id])"
                                    :title="__('baobab::admin.components.delete.title', ['label' => $menu->name])"
                                    :description="trans_choice('baobab::admin.components.delete.menu', $menu->items_count, ['count' => $menu->items_count])"
                                    :label="__('baobab::admin.menus.delete_action')"
                                />
                            </div>
                        </div>
                    </x-baobab::card>
                @endforeach
            </div>
        @endif
    </x-baobab::page>
@endsection
