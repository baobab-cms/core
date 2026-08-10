@extends('baobab::layouts.admin')

@section('title', __('baobab::admin.studio.title'))

@section('content')
    <x-baobab::page
        :title="$draft->title"
        :breadcrumbs="[[__('baobab::admin.sidebar.studio'), route('admin.studio.index')]]"
    >
        <p class="mb-4 rounded-md border border-warning/30 bg-warning/5 px-3 py-2 text-sm text-foreground">
            {{ __('baobab::admin.studio.conflicts.intro') }}
        </p>

        <form method="POST" action="{{ route('admin.studio.generate', $draft) }}">
            @csrf
            {{--
                Marque le choix comme fait : sans ce drapeau, la génération
                redétournerait vers cet écran au lieu de l'appliquer.
            --}}
            <input type="hidden" name="resolved" value="1">

            <div class="space-y-3">
                @foreach ($conflicts as $path => $conflict)
                    <div class="overflow-hidden rounded-lg border border-border bg-surface">
                        <label class="flex items-center gap-2 border-b border-border px-4 py-3">
                            <input type="checkbox" name="overwrite[]" value="{{ $path }}" class="rounded border-border">
                            <span class="font-mono text-sm text-foreground">{{ $path }}</span>
                        </label>

                        @if ($conflict['diff'] === null)
                            <p class="px-4 py-3 text-sm text-muted">{{ __('baobab::admin.studio.conflicts.too_large') }}</p>
                        @else
                            {{--
                                Deux teintes au maximum, jamais un thème de coloration
                                (direction visuelle §6.2) : ce qui disparaît, ce qui arrive,
                                le reste en atténué.
                            --}}
                            <pre class="overflow-x-auto bg-surface-subtle p-4 font-mono text-sm leading-normal">@foreach ($conflict['diff'] as $line)<span @class([
                                'block',
                                'text-danger' => $line['type'] === 'removed',
                                'text-success' => $line['type'] === 'added',
                                'text-muted' => $line['type'] === 'kept',
                            ])>{{ $line['marker'] }} {{ $line['line'] }}</span>@endforeach</pre>
                        @endif
                    </div>
                @endforeach
            </div>

            <p class="mt-4 text-xs text-muted">{{ __('baobab::admin.studio.conflicts.hint') }}</p>

            <div class="mt-10 flex items-center justify-between">
                <x-baobab::button :href="route('admin.studio.step.show', [$draft, 9])" variant="ghost">
                    {{ __('baobab::admin.studio.conflicts.cancel') }}
                </x-baobab::button>

                <x-baobab::button type="submit" variant="danger">
                    {{ __('baobab::admin.studio.conflicts.confirm') }}
                </x-baobab::button>
            </div>
        </form>
    </x-baobab::page>
@endsection
