@extends('baobab::layouts.admin')

@section('title', __('baobab::admin.studio.title'))

@section('content')
    <x-baobab::page
        :title="$draft?->title ?? __('baobab::admin.studio.new_module')"
        :breadcrumbs="[[__('baobab::admin.sidebar.studio'), route('admin.studio.index')]]"
    >
        <x-baobab::wizard :steps="$steps" />

        @if ($blueprintError)
            {{--
                Le blueprint ne passe pas la validation stricte de génération.
                Le contrôle est joué à l'affichage pour que le problème se voie
                avant le clic, pas après.
            --}}
            <x-baobab::card :header="__('baobab::admin.studio.recap.not_generatable')">
                <p class="text-sm text-foreground">{{ $blueprintError }}</p>
                <p class="mt-2 text-xs text-muted">{{ __('baobab::admin.studio.recap.not_generatable_hint') }}</p>
            </x-baobab::card>
        @elseif ($draft->isGenerated())
            <x-baobab::card :header="__('baobab::admin.studio.recap.already_generated')">
                <p class="text-sm text-foreground">
                    {{ __('baobab::admin.studio.recap.already_generated_at', ['date' => $draft->generated_at->format('d/m/Y à H:i')]) }}
                </p>
                <p class="mt-2 font-mono text-sm text-muted">{{ $moduleDir }}</p>
                <p class="mt-2 text-xs text-muted">{{ __('baobab::admin.studio.recap.already_generated_hint') }}</p>
            </x-baobab::card>
        @else
            <p class="mb-4 text-sm text-muted">{{ __('baobab::admin.studio.recap.intro') }}</p>

            {{-- Répertoire cible, artefact réel --}}
            <div class="mb-4">
                <p class="mb-1 text-xs font-medium text-muted">{{ __('baobab::admin.studio.recap.target_directory') }}</p>
                <p class="font-mono text-sm text-foreground">{{ $moduleDir }}</p>
            </div>

            {{-- L'arborescence complète, en bloc mono --}}
            <div class="mb-4">
                <p class="mb-1 text-xs font-medium text-muted">
                    {{ __('baobab::admin.studio.recap.file_count', ['count' => count($files)]) }}
                </p>

                <div class="overflow-x-auto rounded-lg border border-border bg-surface p-4">
                    <ul class="font-mono text-sm leading-normal">
                        @foreach ($tree as $directory => $filenames)
                            <li class="text-muted">{{ $directory === '.' ? '.' : $directory.'/' }}</li>
                            @foreach ($filenames as $filename)
                                <li class="pl-4 text-foreground">{{ $filename }}</li>
                            @endforeach
                        @endforeach
                    </ul>
                </div>

                <p class="mt-1 text-xs text-muted">{{ __('baobab::admin.studio.recap.migration_timestamp_hint') }}</p>
            </div>

            {{-- Prévisualisation du contenu, un dépliant par fichier --}}
            <div class="mb-6" x-data="{ open: null }">
                <p class="mb-2 text-xs font-medium text-muted">{{ __('baobab::admin.studio.recap.preview') }}</p>

                <div class="space-y-1">
                    @foreach ($files as $path => $contents)
                        <div class="overflow-hidden rounded-md border border-border bg-surface">
                            <button
                                type="button"
                                class="flex w-full items-center gap-2 px-3 py-2 text-left"
                                x-on:click="open = open === '{{ $path }}' ? null : '{{ $path }}'"
                                x-bind:aria-expanded="open === '{{ $path }}' ? 'true' : 'false'"
                            >
                                <span
                                    class="flex shrink-0 text-muted transition-transform"
                                    x-bind:class="open === '{{ $path }}' ? 'rotate-90' : ''"
                                >
                                    <x-baobab::icon name="bi-chevron-right" class="h-4 w-4" />
                                </span>
                                <span class="font-mono text-sm text-foreground">{{ $path }}</span>
                            </button>

                            <pre
                                class="overflow-x-auto border-t border-border bg-surface-subtle p-4 font-mono text-sm leading-normal text-foreground"
                                x-show="open === '{{ $path }}'"
                                x-cloak
                            >{{ $contents }}</pre>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="mt-10 flex items-center justify-between">
            <x-baobab::button :href="route('admin.studio.step.show', [$draft, 8])" variant="ghost">
                {{ __('baobab::admin.studio.previous') }}
            </x-baobab::button>

            @if (! $blueprintError && ! $draft->isGenerated())
                <form method="POST" action="{{ route('admin.studio.generate', $draft) }}">
                    @csrf
                    <x-baobab::button type="submit" variant="primary">
                        {{ __('baobab::admin.studio.recap.generate_action') }}
                    </x-baobab::button>
                </form>
            @else
                <x-baobab::button :href="route('admin.studio.index')" variant="secondary">
                    {{ __('baobab::admin.studio.back_to_list') }}
                </x-baobab::button>
            @endif
        </div>
    </x-baobab::page>
@endsection
