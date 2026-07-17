@if (! empty($data['items']))
    <nav>
        @include('baobab::menus.tree', ['items' => $data['items'], 'maxDepth' => null, 'currentDepth' => 1])
    </nav>
@endif
