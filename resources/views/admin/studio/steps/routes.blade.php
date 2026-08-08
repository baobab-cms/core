@extends('baobab::layouts.admin')

@section('title', __('baobab::admin.studio.title'))

@section('content')
    <x-baobab::page
        :title="$draft?->title ?? __('baobab::admin.studio.new_module')"
        :breadcrumbs="[[__('baobab::admin.sidebar.studio'), route('admin.studio.index')]]"
    >
        <x-baobab::wizard :steps="$steps" />

        <x-baobab::form method="POST" action="{{ $formAction }}">
            <p class="mb-4 text-sm text-muted">{{ __('baobab::admin.studio.routes.intro') }}</p>

            @if (empty($routeEntities))
                <p class="mb-4 rounded-md border border-border bg-surface-subtle px-3 py-6 text-center text-sm text-muted">
                    {{ __('baobab::admin.studio.routes.no_entities') }}
                </p>
            @endif

            <div class="space-y-3">
                @foreach ($routeEntities as $entity)
                    <div class="rounded-lg border border-border bg-surface p-4">
                        <h3 class="mb-3 font-mono text-sm font-medium text-foreground">{{ $entity['key'] }}</h3>

                        <div class="space-y-3">
                            @foreach ($entity['surfaces'] as $surface => $preview)
                                <div class="rounded-md border border-border bg-surface-subtle p-3">
                                    {{--
                                        Champ caché avant la case : une case décochée n'est pas
                                        envoyée par le navigateur, sans lui l'étape ne saurait pas
                                        distinguer « décoché » de « entité absente du formulaire ».
                                    --}}
                                    <input type="hidden" name="routes[{{ $entity['key'] }}][{{ $surface }}]" value="0">

                                    <label class="flex items-center gap-2 text-sm font-medium text-foreground">
                                        <input type="checkbox" name="routes[{{ $entity['key'] }}][{{ $surface }}]" value="1" @checked($values['routes'][$entity['key']][$surface]) class="rounded border-border">
                                        {{ __("baobab::admin.studio.routes.surface_{$surface}") }}
                                    </label>

                                    <p class="mt-1 ml-6 text-xs text-muted">{{ __("baobab::admin.studio.routes.surface_{$surface}_hint") }}</p>

                                    <div class="mt-2 ml-6 grid gap-3 sm:grid-cols-2">
                                        <div>
                                            <p class="mb-1 text-xs font-medium text-muted">{{ __('baobab::admin.studio.routes.uris') }}</p>
                                            <ul class="space-y-0.5 font-mono text-sm text-foreground">
                                                @foreach ($preview['uris'] as $uri)
                                                    <li>/{{ $uri }}</li>
                                                @endforeach
                                            </ul>
                                        </div>

                                        <div>
                                            <p class="mb-1 text-xs font-medium text-muted">{{ __('baobab::admin.studio.routes.files') }}</p>
                                            <ul class="space-y-0.5 font-mono text-sm text-muted">
                                                @foreach ($preview['files'] as $file)
                                                    <li>{{ $file }}</li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-10 flex items-center justify-between">
                <x-baobab::button :href="route('admin.studio.step.show', [$draft, 3])" variant="ghost">
                    {{ __('baobab::admin.studio.previous') }}
                </x-baobab::button>

                <x-baobab::button type="submit" variant="primary">
                    {{ $isLastImplementedStep ? __('baobab::admin.studio.save') : __('baobab::admin.studio.save_and_continue') }}
                </x-baobab::button>
            </div>
        </x-baobab::form>
    </x-baobab::page>
@endsection
