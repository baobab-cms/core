@foreach ($items as $item)
    @if (count($item->children) > 0)
        {{--
            Replié par défaut (`pinned` démarre à `false`, suivi n° 383) : au
            survol, `hovering` déplie temporairement sans écrire dans
            localStorage ; un clic bascule `pinned`, qui persiste comme avant.
            `open` reste un getter dérivé des deux — la seule chose qu'expose
            le HTML aux tests (`x-bind:aria-expanded="open ? ..."`) ne bouge
            pas, seule sa source change.
        --}}
        <li
            x-data="{
                pinned: (localStorage.getItem('baobab.sidebar.{{ $item->id }}') ?? 'false') === 'true',
                hovering: false,
                get open() { return this.pinned || this.hovering },
            }"
            x-on:mouseenter="hovering = true"
            x-on:mouseleave="hovering = false"
        >
            {{--
                Le groupe lui-même n'est pas « la page courante » (`aria-current="page"`
                mentirait, il ne mène nulle part) : `aria-current="true"` (valeur
                générique de la spec ARIA) porte la même information — un enfant de
                ce groupe est la page affichée — que le groupe soit déplié ou non
                (suivi n° 383).
            --}}
            <button
                type="button"
                @click="pinned = !pinned; localStorage.setItem('baobab.sidebar.{{ $item->id }}', pinned)"
                class="flex w-full items-center gap-2 rounded-md px-3 py-1.5 text-left text-sm hover:bg-surface {{ $item->isActive ? 'bg-surface font-medium text-primary' : 'text-foreground' }}"
                title="{{ $item->label }}"
                aria-label="{{ $item->label }}"
                x-bind:aria-expanded="open ? 'true' : 'false'"
                @if ($item->isActive) aria-current="true" @endif
            >
                <x-baobab::icon :name="$item->icon ?? 'bi-app-indicator'" class="h-4 w-4 shrink-0" />
                <span class="lg:group-data-[collapsed]:hidden">{{ $item->label }}</span>
            </button>

            <ul x-show="open" x-cloak class="ml-3 space-y-1 border-l border-border pl-3">
                @include('baobab::layouts.partials.admin-sidebar-items', ['items' => $item->children])
            </ul>
        </li>
    @else
        <li>
            <a
                href="{{ $item->url ?? '#' }}"
                class="flex items-center gap-2 rounded-md px-3 py-1.5 text-sm hover:bg-surface {{ $item->isActive ? 'bg-surface font-medium text-primary' : 'text-foreground' }}"
                title="{{ $item->label }}"
                aria-label="{{ $item->label }}"
                @if ($item->isActive) aria-current="page" @endif
            >
                <x-baobab::icon :name="$item->icon ?? 'bi-app-indicator'" class="h-4 w-4 shrink-0" />
                <span class="lg:group-data-[collapsed]:hidden">{{ $item->label }}</span>
            </a>
        </li>
    @endif
@endforeach
