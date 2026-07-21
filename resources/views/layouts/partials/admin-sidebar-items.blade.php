@foreach ($items as $item)
    <li x-data="{ open: (localStorage.getItem('baobab.sidebar.{{ $item->id }}') ?? 'true') === 'true' }">
        @if (count($item->children) > 0)
            <button
                type="button"
                @click="open = !open; localStorage.setItem('baobab.sidebar.{{ $item->id }}', open)"
                class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-left text-sm text-foreground hover:bg-surface"
                title="{{ $item->label }}"
                aria-label="{{ $item->label }}"
            >
                <x-baobab::icon :name="$item->icon ?? 'bi-app-indicator'" class="h-5 w-5 shrink-0" />
                <span class="lg:group-data-[collapsed]:hidden">{{ $item->label }}</span>
            </button>

            <ul x-show="open" x-cloak class="ml-3 space-y-1 border-l border-border pl-3">
                @include('baobab::layouts.partials.admin-sidebar-items', ['items' => $item->children])
            </ul>
        @else
            <a
                href="{{ $item->url ?? '#' }}"
                class="flex items-center gap-2 rounded-md px-3 py-2 text-sm text-foreground hover:bg-surface"
                title="{{ $item->label }}"
                aria-label="{{ $item->label }}"
            >
                <x-baobab::icon :name="$item->icon ?? 'bi-app-indicator'" class="h-5 w-5 shrink-0" />
                <span class="lg:group-data-[collapsed]:hidden">{{ $item->label }}</span>
            </a>
        @endif
    </li>
@endforeach
