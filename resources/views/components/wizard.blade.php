@props([
    'steps' => [],
])

{{--
    Composant générique de navigation par étapes (spec-admin §4.1, vague 2 —
    suivi n° 6). Ne porte aucune logique : le tableau `$steps` est précalculé
    par le contrôleur appelant (ex. `StudioController::navSteps()`), pas de
    `@php` ici (règle « pas de logique dans les vues admin »). Une étape sans
    `url` (non atteignable — pas encore franchie ou pas encore implémentée) se
    rend en simple libellé, jamais un lien.
--}}
<nav aria-label="{{ __('baobab::admin.wizard.nav_label') }}" {{ $attributes->class(['mb-6']) }}>
    <ol class="flex flex-wrap items-center gap-x-1 gap-y-3">
        @foreach ($steps as $step)
            <li class="flex items-center">
                @if ($step['url'])
                    <a
                        href="{{ $step['url'] }}"
                        @if ($step['status'] === 'current') aria-current="step" @endif
                        class="flex items-center gap-2 rounded-full px-3 py-1.5 text-sm font-medium transition-colors
                            {{ $step['status'] === 'current' ? 'bg-primary text-white' : 'bg-surface-subtle text-foreground hover:bg-sand-100' }}"
                    >
                        <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-white text-xs
                            {{ $step['status'] === 'current' ? 'text-primary' : 'text-foreground' }}">
                            @if ($step['status'] === 'completed')
                                <x-baobab::icon name="bi-check2" class="h-3.5 w-3.5" />
                            @else
                                {{ $step['number'] }}
                            @endif
                        </span>
                        {{ $step['label'] }}
                    </a>
                @else
                    <span class="flex items-center gap-2 rounded-full bg-surface-subtle px-3 py-1.5 text-sm text-muted opacity-60">
                        <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-white text-xs text-muted">
                            {{ $step['number'] }}
                        </span>
                        {{ $step['label'] }}
                    </span>
                @endif
            </li>

            @if (! $loop->last)
                <li aria-hidden="true" class="hidden h-px w-4 bg-border sm:block"></li>
            @endif
        @endforeach
    </ol>
</nav>

{{ $slot }}
