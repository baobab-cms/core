<ul role="menu">
    @foreach ($items as $item)
        <li role="none" class="{{ $item['has-children'] ? 'has-children' : '' }} {{ $item['is-active'] ? 'is-active' : '' }} {{ $item['is-ancestor'] ? 'is-ancestor' : '' }}">
            @if ($item['url'] !== null)
                <a
                    role="menuitem"
                    href="{{ $item['url'] }}"
                    target="{{ $item['target'] }}"
                    @class([$item['css_class'] ?? ''])
                    @if ($item['is-active']) aria-current="page" @endif
                >
                    @if ($item['icon'])<x-baobab::icon :name="$item['icon']" class="h-4 w-4" />@endif
                    {{ $item['label'] }}
                </a>
            @else
                <span role="presentation" @class([$item['css_class'] ?? ''])>{{ $item['label'] }}</span>
            @endif

            @if ($item['has-children'] && ($maxDepth === null || $currentDepth < $maxDepth))
                @include('baobab::menus.tree', ['items' => $item['children'], 'maxDepth' => $maxDepth, 'currentDepth' => $currentDepth + 1])
            @endif
        </li>
    @endforeach
</ul>
