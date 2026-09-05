@extends('baobab::layouts.admin')

@section('title', __('baobab::admin.demo_content.title'))

@section('content')
    <x-baobab::page :title="__('baobab::admin.demo_content.title')">
        <x-baobab::card>
            <p class="text-sm text-muted">{{ __('baobab::admin.demo_content.intro') }}</p>

            <div class="mt-4">
                @if ($present)
                    <h2 class="font-display text-base font-semibold text-foreground">
                        {{ __('baobab::admin.demo_content.present_title') }}
                    </h2>
                    <p class="mt-2 text-sm text-muted">
                        {{ __('baobab::admin.demo_content.present_description') }}
                    </p>

                    <x-baobab::button
                        type="button"
                        variant="danger"
                        class="mt-4"
                        x-data
                        x-on:click="$dispatch('open-modal', 'remove-demo-content')"
                    >
                        {{ __('baobab::admin.demo_content.remove_action') }}
                    </x-baobab::button>
                @else
                    <h2 class="font-display text-base font-semibold text-foreground">
                        {{ __('baobab::admin.demo_content.absent_title') }}
                    </h2>
                    <p class="mt-2 text-sm text-muted">
                        {{ __('baobab::admin.demo_content.absent_description') }}
                    </p>
                @endif
            </div>
        </x-baobab::card>

        @if ($present)
            {{--
                Retrait définitif, sans corbeille (arbitrage D-A, suivi n° 249) :
                la confirmation est donc explicite, patron exact de la modale de
                désinstallation d'un module.
            --}}
            <x-baobab::modal name="remove-demo-content">
                <h2 class="font-display text-base font-semibold text-foreground">
                    {{ __('baobab::admin.demo_content.remove_confirm_title') }}
                </h2>

                <p class="mt-2 text-sm text-muted">
                    {{ __('baobab::admin.demo_content.remove_confirm_description') }}
                </p>

                <x-baobab::form method="DELETE" action="{{ route('admin.demo-content.destroy') }}" class="mt-4">
                    <div class="flex justify-end gap-2">
                        <x-baobab::button type="button" variant="ghost" x-on:click="show = false">
                            {{ __('baobab::admin.components.close') }}
                        </x-baobab::button>

                        <x-baobab::button type="submit" variant="danger">
                            {{ __('baobab::admin.demo_content.remove_action') }}
                        </x-baobab::button>
                    </div>
                </x-baobab::form>
            </x-baobab::modal>
        @endif
    </x-baobab::page>
@endsection
