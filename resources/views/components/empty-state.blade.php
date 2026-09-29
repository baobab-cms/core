{{--
    État vide (`direction-visuelle.md` §9) : titre court, une phrase, une
    action primaire. `title` optionnel (adoption incrémentale, suivi n° 379
    décision 2) — les appels existants sans titre gardent leur rendu actuel,
    `message` seul. Le motif de tuile que §9 autorise ici reste hors
    périmètre : aucun asset de ce type n'existe dans le dépôt (même lacune
    que le logo, suivi n° 375).
--}}
@props([
    'title' => null,
    'message' => __('baobab::admin.components.no_results'),
])

<div {{ $attributes->class(['flex flex-col items-center gap-3 px-6 py-12 text-center']) }}>
    @if ($title)
        <h3 class="font-display text-lg font-medium leading-tight text-foreground">{{ $title }}</h3>
    @endif

    <p class="text-sm text-muted">{{ $message }}</p>

    @isset($action)
        <div>{{ $action }}</div>
    @endisset
</div>
