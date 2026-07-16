@extends('baobab::layouts.admin')

@section('title', __('baobab::admin.themes.title'))

@section('content')
    <x-baobab::page :title="__('baobab::admin.themes.title')">
        @if ($themes->isEmpty())
            <x-baobab::empty-state :message="__('baobab::admin.themes.empty')" />
        @else
            <div class="space-y-4">
                @foreach ($themes as $theme)
                    <x-baobab::card :header="$theme->title">
                        <div class="flex items-center justify-between gap-4">
                            <div class="space-y-1">
                                @if ($theme->status === 'active')
                                    <x-baobab::badge variant="success">{{ __('baobab::admin.themes.active_label') }}</x-baobab::badge>
                                @else
                                    <x-baobab::badge variant="neutral">{{ __('baobab::admin.themes.inactive_label') }}</x-baobab::badge>
                                @endif

                                @if ($theme->manifest['theme']['parent'] ?? null)
                                    <p class="text-xs text-muted">
                                        {{ __('baobab::admin.themes.parent_label', ['parent' => $theme->manifest['theme']['parent']]) }}
                                    </p>
                                @endif
                            </div>

                            @unless ($theme->status === 'active')
                                <div class="flex items-center gap-3">
                                    <a href="{{ route('admin.themes.preview', ['theme' => $theme->id]) }}" class="text-muted hover:underline">
                                        {{ __('baobab::admin.themes.preview_action') }}
                                    </a>

                                    <form method="POST" action="{{ route('admin.themes.activate', ['theme' => $theme->id]) }}">
                                        @csrf
                                        <x-baobab::button type="submit" variant="primary">
                                            {{ __('baobab::admin.themes.activate_action') }}
                                        </x-baobab::button>
                                    </form>
                                </div>
                            @endunless
                        </div>
                    </x-baobab::card>
                @endforeach
            </div>
        @endif
    </x-baobab::page>
@endsection
