{{--
    Ni `role="menu"`, ni `role="none"`, ni `role="menuitem"` (suivi n° 307
    constat 5) : ces rôles annoncent une barre de menus applicative avec un
    patron d'interaction au clavier (flèches, Home/End, roving tabindex)
    qu'aucun script ne fournit ici — une simple navigation de site
    (`<ul><li><a>`) n'a besoin d'aucun rôle explicite, la sémantique native
    suffit et se comporte déjà correctement avec Tab.
--}}
<ul>
    @foreach ($items as $item)
        <li class="{{ $item['has-children'] ? 'has-children' : '' }} {{ $item['is-active'] ? 'is-active' : '' }} {{ $item['is-ancestor'] ? 'is-ancestor' : '' }}">
            @if ($item['url'] !== null)
                <a
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
