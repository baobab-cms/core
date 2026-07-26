@extends('baobab::layouts.admin')

@section('title', __('baobab::admin.themes.studio.title'))

@section('content')
    <x-baobab::page :title="__('baobab::admin.themes.studio.title')">
        <x-slot:actions>
            <x-baobab::button href="{{ route('admin.themes.studio.create') }}" variant="primary">
                {{ __('baobab::admin.themes.studio.create_action') }}
            </x-baobab::button>
        </x-slot:actions>

        <p class="mb-4 text-sm text-muted">{{ __('baobab::admin.themes.studio.intro') }}</p>

        @if ($blueprints->isEmpty())
            <x-baobab::empty-state :message="__('baobab::admin.themes.studio.empty')" />
        @else
            <div class="space-y-4">
                @foreach ($blueprints as $blueprint)
                    <x-baobab::card :header="$blueprint['name'] ?? $blueprint['slug']">
                        <div class="flex items-center justify-between gap-4">
                            <p class="text-sm text-muted">
                                {{ __('baobab::admin.themes.studio.supports_summary', ['supports' => empty($blueprint['supports']) ? '—' : implode(', ', $blueprint['supports'])]) }}
                            </p>

                            <a href="{{ route('admin.themes.studio.edit', ['slug' => $blueprint['slug']]) }}" class="text-primary hover:underline">
                                {{ __('baobab::admin.themes.studio.edit_action') }}
                            </a>
                        </div>
                    </x-baobab::card>
                @endforeach
            </div>
        @endif
    </x-baobab::page>
@endsection
