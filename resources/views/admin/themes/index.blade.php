@extends('baobab::layouts.admin')

@section('title', __('baobab::admin.themes.title'))

@section('content')
    <x-baobab::page :title="__('baobab::admin.themes.title')">
        <x-slot:actions>
            <x-baobab::button href="{{ route('admin.themes.studio.index') }}" variant="secondary">
                {{ __('baobab::admin.themes.studio.link') }}
            </x-baobab::button>
        </x-slot:actions>

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

                            @if ($theme->status === 'active')
                                <x-baobab::button
                                    type="button"
                                    variant="secondary"
                                    x-data
                                    x-on:click="$dispatch('open-modal', 'deactivate-theme-{{ $theme->id }}')"
                                >
                                    {{ __('baobab::admin.themes.deactivate_action') }}
                                </x-baobab::button>
                            @else
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
                            @endif
                        </div>
                    </x-baobab::card>

                    @if ($theme->status === 'active')
                        {{--
                            Désactiver n'est pas anodin : le site public bascule aussitôt sur le
                            rendu de repli du Core, que la spec 19 §6.1 veut transitoire et signalé.
                            La confirmation dit donc la conséquence, plutôt que de demander « êtes-vous
                            sûr ? ». Aucune donnée n'est perdue, aucun fichier n'est retiré : le
                            bouton reste secondaire, pas danger.
                        --}}
                        <x-baobab::modal name="deactivate-theme-{{ $theme->id }}">
                            <h2 class="font-display text-base font-semibold text-foreground">
                                {{ __('baobab::admin.themes.deactivate_confirm_title', ['theme' => $theme->title]) }}
                            </h2>

                            <p class="mt-2 text-sm text-muted">
                                {{ __('baobab::admin.themes.deactivate_confirm_description') }}
                            </p>

                            <x-baobab::form method="POST" action="{{ route('admin.themes.deactivate', ['theme' => $theme->id]) }}" class="mt-4">
                                <x-baobab::button type="submit" variant="secondary">
                                    {{ __('baobab::admin.themes.deactivate_action') }}
                                </x-baobab::button>
                            </x-baobab::form>
                        </x-baobab::modal>
                    @endif
                @endforeach
            </div>
        @endif
    </x-baobab::page>
@endsection
