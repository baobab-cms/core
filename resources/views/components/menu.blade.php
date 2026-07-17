@if (! empty($items))
    <nav {{ $attributes }}>
        @include('baobab::menus.tree', ['items' => $items, 'maxDepth' => $depth, 'currentDepth' => 1])
    </nav>
@endif
