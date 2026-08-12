{{--
    `<x-baobab::field.auto>` (spec 19 §5.10) — rend le rôle demandé et rien
    d'autre. Toute la résolution est faite dans Baobab\View\Components\Field\Auto :
    cette vue ne fait que dérouler ce qu'elle a reçu.

    Le balisage est volontairement **nu** : éléments sémantiques, aucune classe
    utilitaire, un attribut `data-field` par champ comme seule accroche. Ce
    composant est rendu à l'intérieur d'un thème, dont c'est le métier de
    styler ; un composant du Core qui imposerait ses classes déciderait à la
    place du thème qui l'appelle.

    Un rôle sans matière ne rend rien du tout — pas un conteneur vide, pas une
    étiquette orpheline (§5.9, un vide de chrome disparaît).
--}}
@if ($role === 'image' || $role === 'body')
    @if ($field !== null)
        <div data-role="{{ $role }}">
            <x-dynamic-component :component="$field['component']" :attributes="$field['attributes']" />
        </div>
    @endif
@elseif ($role === 'relations')
    @foreach ($taxonomies as $taxonomy)
        <div data-role="relations">
            <span>{{ $taxonomy['label'] }}</span>
            <ul>
                @foreach ($taxonomy['terms'] as $term)
                    <li>
                        @if ($term['url'] !== null)
                            <a href="{{ $term['url'] }}">{{ $term['title'] }}</a>
                        @else
                            {{ $term['title'] }}
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endforeach
@else
    @if ($fields !== [])
        <dl data-role="rest">
            @foreach ($fields as $item)
                <dt data-field="{{ $item['key'] }}">{{ $item['label'] }}</dt>
                <dd data-field="{{ $item['key'] }}">
                    <x-dynamic-component :component="$item['component']" :attributes="$item['attributes']" />
                </dd>
            @endforeach
        </dl>
    @endif
@endif
