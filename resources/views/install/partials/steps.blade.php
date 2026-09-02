{{--
    Fil des écrans de saisie — Pass C2b.

    Il ne montre **que** la collecte, pas les six étapes du §4 : celles-ci se
    déroulent sur l'écran de progression, et les mêler ici laisserait croire
    que « Compte » est déjà fait alors qu'il est seulement saisi. Ce que cet
    indicateur promet, c'est un nombre d'écrans — pas un avancement.

    L'état de chaque écran est calculé par le contrôleur : une vue affiche, elle
    ne décide pas. `aria-current` porte l'information pour qui n'en voit pas la
    couleur.
--}}
<ol class="steps" aria-label="Étapes de la saisie">
    @foreach ($screens as $screen)
        <li
            class="steps__item is-{{ $screen['state'] }}"
            @if ($screen['state'] === 'current') aria-current="step" @endif
        >{{ $screen['label'] }}</li>
    @endforeach
</ol>
