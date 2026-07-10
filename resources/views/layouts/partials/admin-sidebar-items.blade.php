@foreach ($items as $item)
    <li x-data="{ open: (localStorage.getItem('baobab.sidebar.{{ $item->id }}') ?? 'true') === 'true' }">
        @if (count($item->children) > 0)
            <button
                type="button"
                @click="open = !open; localStorage.setItem('baobab.sidebar.{{ $item->id }}', open)"
                class="flex w-full items-center justify-between rounded-md px-3 py-2 text-left text-sm text-foreground hover:bg-surface"
            >
                <span>{{ $item->label }}</span>
            </button>

            <ul x-show="open" x-cloak class="ml-3 space-y-1 border-l border-border pl-3">
                @include('baobab::layouts.partials.admin-sidebar-items', ['items' => $item->children])
            </ul>
        @else
            <a
                href="{{ $item->url ?? '#' }}"
                class="block rounded-md px-3 py-2 text-sm text-foreground hover:bg-surface"
            >
                {{ $item->label }}
            </a>
        @endif
    </li>
@endforeach
